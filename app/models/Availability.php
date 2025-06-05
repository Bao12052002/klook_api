<?php

class Availability extends Model
{
    protected $table = 'tbl_klook_availability';

    /**
     * Trả về map dữ liệu dạng ['Y-m-d' => row] từ cả dòng range và override
     */
 public function findMergedAvailability($productId, $optionId, $startDate, $endDate)
{
    $sql = "
        SELECT * FROM {$this->table}
        WHERE product_id = ? AND option_id = ?
        AND start_date <= ? AND end_date >= ?
    ";

    $stmt = $this->query($sql, [$productId, $optionId, $endDate, $startDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse to map
    $map = [];

    foreach ($rows as $row) {
        $excluded = [];
        if (!empty($row['excluded_dates'])) {
            $excluded = json_decode($row['excluded_dates'], true);
        }

        $start = new DateTime($row['start_date']);
        $end = new DateTime($row['end_date']);

        while ($start <= $end) {
            $dateStr = $start->format('Y-m-d');
            if (!in_array($dateStr, $excluded)) {
                $map[$dateStr] = $row;
            }
            $start->modify('+1 day');
        }
    }

    return $map;
}

    public function tryHoldSlots(string $productId, string $optionId, string $date, int $paxToHold, PDO $dbConnectionInstance): bool
    {
        if ($paxToHold <= 0) {
            ErrorHelper::logError("HoldSlots: paxToHold must be positive. Value: {$paxToHold}");
            return false;
        }

        // Sửa lại phần JSON_CONTAINS
        $sqlSelect = "SELECT id, available_slots, capacity FROM {$this->table} 
                      WHERE product_id = :product_id 
                      AND option_id = :option_id 
                      AND :target_date BETWEEN start_date AND end_date
                      AND (excluded_dates IS NULL OR NOT JSON_CONTAINS(excluded_dates, :date_string_for_json_search)) -- Bỏ CAST, dùng placeholder mới
                      AND is_blocked = 0
                      ORDER BY start_date DESC, id DESC 
                      LIMIT 1 FOR UPDATE";

        try {
            $stmtSelect = $dbConnectionInstance->prepare($sqlSelect);
            // Giá trị cho :date_string_for_json_search phải là một chuỗi JSON, ví dụ "2025-06-07" (bao gồm cả dấu ngoặc kép)
            // PDO sẽ tự xử lý việc escape chuỗi này.
            // Tuy nhiên, để JSON_CONTAINS tìm một chuỗi bên trong một mảng JSON các chuỗi,
            // giá trị tìm kiếm cần được định dạng như một chuỗi JSON.
            // Ví dụ: nếu $date là "2025-06-07", thì giá trị truyền vào JSON_CONTAINS phải là "\"2025-06-07\"" (chuỗi có dấu nháy kép bên trong)
            $jsonSearchValue = json_encode($date); // Cách này sẽ tạo ra chuỗi JSON đúng, ví dụ: "\"2025-06-07\""

            $params = [
                'product_id' => $productId,
                'option_id' => $optionId,
                'target_date' => $date, // Dùng cho BETWEEN
                'date_string_for_json_search' => $jsonSearchValue // Dùng cho JSON_CONTAINS
            ];
            
            $stmtSelect->execute($params);
            $availRecord = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            if (!$availRecord) {
                ErrorHelper::logError("HoldSlots: No active/valid availability rule found for {$productId}/{$optionId} on {$date}. Cannot hold slots.");
                return false; 
            }

            $currentSlots = (int)$availRecord['available_slots'];
            if ($currentSlots < $paxToHold) {
                ErrorHelper::logError("HoldSlots: Not enough slots. Requested: {$paxToHold}, Available: {$currentSlots} for {$productId}/{$optionId} on {$date}.");
                return false; 
            }

            $newSlots = $currentSlots - $paxToHold;
            $sqlUpdate = "UPDATE {$this->table} SET available_slots = :new_slots 
                          WHERE id = :id AND available_slots = :expected_current_slots"; 
            
            $stmtUpdate = $dbConnectionInstance->prepare($sqlUpdate);
            $updateSuccess = $stmtUpdate->execute([
                'new_slots' => $newSlots,
                'id' => $availRecord['id'],
                'expected_current_slots' => $currentSlots 
            ]);

            if (!$updateSuccess || $stmtUpdate->rowCount() === 0) {
                 ErrorHelper::logError("HoldSlots: Failed to update slots (optimistic lock may have failed or DB error) for ID {$availRecord['id']}. Requested: {$paxToHold}. Current slots in DB might have changed from {$currentSlots}.");
                 return false;
            }
            
            ErrorHelper::logError("HoldSlots: Successfully held {$paxToHold} slots for {$productId}/{$optionId} on {$date}. New available_slots: {$newSlots}. Record ID: {$availRecord['id']}");
            return true;

        } catch (\PDOException $e) {
            ErrorHelper::logError("HoldSlots PDOException: " . $e->getMessage() . " for {$productId}/{$optionId} on {$date}. SQL attempted: " . $sqlSelect . " with params: " . json_encode($params ?? []));
            return false;
        }
    }

    // Phương thức releaseSlots giữ nguyên (nó không dùng JSON_CONTAINS)
    public function releaseSlots(string $productId, string $optionId, string $date, int $paxToRelease, PDO $dbConnectionInstance): bool
    {
        // ... (code của releaseSlots giữ nguyên như lần trước) ...
        if ($paxToRelease <= 0) {
             ErrorHelper::logError("ReleaseSlots: paxToRelease must be positive. Value: {$paxToRelease}");
            return false;
        }
        $sqlSelect = "SELECT id, available_slots, capacity FROM {$this->table} 
                      WHERE product_id = :product_id AND option_id = :option_id 
                      AND :target_date BETWEEN start_date AND end_date
                      ORDER BY start_date DESC, id DESC
                      LIMIT 1 FOR UPDATE";
        try {
            $stmtSelect = $dbConnectionInstance->prepare($sqlSelect);
            $stmtSelect->execute([
                'product_id' => $productId,
                'option_id' => $optionId,
                'target_date' => $date
            ]);
            $availRecord = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            if (!$availRecord) {
                ErrorHelper::logError("ReleaseSlots: No availability rule found for {$productId}/{$optionId} on {$date}. Cannot release.");
                return false; 
            }
            $newSlots = (int)$availRecord['available_slots'] + $paxToRelease;
            $capacity = (int)$availRecord['capacity'];
            if ($capacity > 0 && $newSlots > $capacity) {
                ErrorHelper::logError("ReleaseSlots: Calculated new_slots {$newSlots} exceeds capacity {$capacity} for {$productId}/{$optionId} on {$date}. Setting to capacity.");
                $newSlots = $capacity;
            }
            $sqlUpdate = "UPDATE {$this->table} SET available_slots = :new_slots WHERE id = :id";
            $stmtUpdate = $dbConnectionInstance->prepare($sqlUpdate);
            $updateSuccess = $stmtUpdate->execute(['new_slots' => $newSlots, 'id' => $availRecord['id']]);

            if (!$updateSuccess) {
                 ErrorHelper::logError("ReleaseSlots: Failed to update slots for ID {$availRecord['id']}.");
                 return false;
            }
            ErrorHelper::logError("ReleaseSlots: Successfully released {$paxToRelease} slots for {$productId}/{$optionId} on {$date}. New available_slots: {$newSlots}. Record ID: {$availRecord['id']}");
            return true;
        } catch (\PDOException $e) {
            ErrorHelper::logError("ReleaseSlots PDOException: " . $e->getMessage() . " for {$productId}/{$optionId} on {$date}");
            return false;
        }
    }
// Unit.php
public function findUnitIdsByProductAndOption($productId, $optionId) {
    $sql = "SELECT type FROM tbl_klook_units WHERE product_id = ? AND option_id = ?";
    $stmt = $this->query($sql, [$productId, $optionId]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'type');
}

public function addExcludedDate($productId, $optionId, $dateToExclude)
{
    $sql = "SELECT * FROM {$this->table} 
            WHERE product_id = ? AND option_id = ? 
            AND start_date <= ? AND end_date >= ?";
    
    $stmt = $this->query($sql, [$productId, $optionId, $dateToExclude, $dateToExclude]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;

    $excluded = [];
    if (!empty($row['excluded_dates'])) {
        $excluded = json_decode($row['excluded_dates'], true);
    }

    if (!in_array($dateToExclude, $excluded)) {
        $excluded[] = $dateToExclude;
    }

    return $this->update($row['id'], [
        'excluded_dates' => json_encode($excluded)
    ]);
}

public function removeExcludedDate($productId, $optionId, $dateToInclude)
{
    $sql = "SELECT * FROM {$this->table} 
            WHERE product_id = ? AND option_id = ? 
            AND start_date <= ? AND end_date >= ?";
    
    $stmt = $this->query($sql, [$productId, $optionId, $dateToInclude, $dateToInclude]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;

    $excluded = [];
    if (!empty($row['excluded_dates'])) {
        $excluded = json_decode($row['excluded_dates'], true);
    }

    $newExcluded = array_values(array_filter($excluded, fn($d) => $d !== $dateToInclude));

    return $this->update($row['id'], [
        'excluded_dates' => json_encode($newExcluded)
    ]);
}

    /**
     * Tạo hoặc cập nhật dữ liệu override theo ngày cụ thể
     */
    public function upsertOverrideAvailability($productId, $optionId, $overrideDate, $slots, $capacity = null, $isBlocked = false)
    {
        $sqlCheck = "SELECT id FROM {$this->table} WHERE product_id = ? AND option_id = ? AND override_date = ?";
        $stmt = $this->query($sqlCheck, [$productId, $optionId, $overrideDate]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $data = [
            'product_id' => $productId,
            'option_id' => $optionId,
            'override_date' => $overrideDate,
            'available_slots' => $slots,
            'capacity' => $capacity ?? $slots,
            'is_blocked' => $isBlocked ? 1 : 0,
        ];

        if ($existing) {
            return $this->update($existing['id'], $data);
        } else {
            return $this->create($data);
        }
    }

    /**
     * Giảm slot ngày cụ thể (override)
     */
    public function reduceOverrideAvailability($productId, $optionId, $date, $quantity = 1)
    {
        $sql = "
            UPDATE {$this->table}
            SET available_slots = available_slots - ?
            WHERE product_id = ? AND option_id = ? AND override_date = ? AND available_slots >= ?
        ";
        $stmt = $this->query($sql, [$quantity, $productId, $optionId, $date, $quantity]);
        return $stmt->rowCount() > 0;
    }
}