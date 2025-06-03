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