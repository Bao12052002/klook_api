<?php
// File: app/models/Booking.php
class Booking extends Model {
    protected $table = 'tbl_klook_bookings';

    public function createKlookReservation(
        string $productId, string $optionId, string $klookAvailabilityId,
        array $parsedUnitItems, // Format: ['db_unit_id' => ['quantity' => X, 'details' => DB_unit_object, 'uuids' => [...], 'pax_count_per_unit' => Y]]
        string $targetCurrency, ?array $calculatedBookingPricingDetails,
        ?array $unitItemPricesForDb, // Map [db_unit_id => KlookPricingObject] cho từng loại unit
        ?string $bookingKlookUUID, ?string $notes, int $expirationMinutes,
        int $totalPaxToHold 
    ) {
        $internalBookingId = 'BKG' . strtoupper(uniqid()); 
        $utcCreatedAt = gmdate('Y-m-d H:i:s'); 
        $utcExpiresAtDateTime = new \DateTime($utcCreatedAt, new \DateTimeZone('UTC'));
        $utcExpiresAtDateTime->add(new \DateInterval('PT' . $expirationMinutes . 'M'));
        $utcExpiresAt = $utcExpiresAtDateTime->format('Y-m-d H:i:s');
        
        $slotDateTimeForDb = null;
        try {
            $slotDateTimeForDb = new \DateTime($klookAvailabilityId); 
        } catch (\Exception $e) {
            ErrorHelper::logError("CreateKlookReservation: Invalid klookAvailabilityId format {$klookAvailabilityId}.");
            throw new \Exception("Invalid availabilityId format.", 400); // Ném lỗi để controller bắt
        }
        $slotDateForHold = $slotDateTimeForDb->format('Y-m-d');

        $this->db->beginTransaction();
        try {
            $availabilityModel = new Availability(); 
            $holdSuccessful = $availabilityModel->tryHoldSlots(
                $productId, $optionId, $slotDateForHold, $totalPaxToHold, $this->db
            );

            if (!$holdSuccessful) {
                $this->db->rollBack();
                ErrorHelper::logError("Booking Creation Failed: Could not hold slots. Prod:{$productId}, Opt:{$optionId}, Date:{$slotDateForHold}, Pax:{$totalPaxToHold}. Tx Rolled Back.");
                throw new \Exception("Failed to secure availability (slots could not be held).", 503);
            }
            ErrorHelper::logError("Booking Creation: Slots held for {$totalPaxToHold} pax for new booking {$internalBookingId}.");

            $bookingDbData = [
                'id' => $internalBookingId, 'uuid' => $bookingKlookUUID ?? $internalBookingId,
                'product_id' => $productId, 'option_id' => $optionId,
                'klook_availability_id' => $klookAvailabilityId, 'status' => 'ON_HOLD', 
                'notes' => $notes, 'currency' => $targetCurrency,
                'total_amount' => $calculatedBookingPricingDetails['retail'] ?? 0,
                'booking_pricing_details' => json_encode($calculatedBookingPricingDetails),
                'utc_created_at' => $utcCreatedAt, 'utc_expires_at' => $utcExpiresAt,
                'test_mode' => false, 'customer_name' => '', 'customer_email' => '',
                'booking_date' => $slotDateForHold, 'start_time' => $slotDateTimeForDb->format('H:i:s'),
                'supplier_reference' => $internalBookingId . '-S',
                'utc_confirmed_at' => null, 'utc_redeemed_at' => null, 'cancellable' => true,
            ];
            $bookingDbData = array_filter($bookingDbData, function($value) { return $value !== null || is_bool($value) || is_numeric($value); });
            
            $fields = implode(', ', array_keys($bookingDbData));
            $placeholders = ':' . implode(', :', array_keys($bookingDbData));
            $sqlBooking = "INSERT INTO {$this->table} (" . $fields . ") VALUES (" . $placeholders . ")";
            $this->query($sqlBooking, $bookingDbData);

            foreach ($parsedUnitItems as $dbUnitId => $itemData) {
                $unitPriceDetailForThisItem = $unitItemPricesForDb[$dbUnitId] ?? null;
                $numItemsForThisUnitId = $itemData['quantity'];
                for ($i = 0; $i < $numItemsForThisUnitId; $i++) {
                    $unitItemUUID = $itemData['uuids'][$i] ?? uniqid('klookunititem-'. substr($dbUnitId,0,10) . '-');
                    $bookingUnitData = [
                        'booking_id' => $internalBookingId, 'unit_item_uuid' => $unitItemUUID,
                        'unit_id' => $dbUnitId, 'quantity' => 1, 
                        'pax_count' => (int)($itemData['details']['pax_count'] ?? 1),
                        'status' => 'ON_HOLD',
                        'unit_price' => $unitPriceDetailForThisItem['retail'] ?? 0, 
                        'unit_item_pricing_details' => json_encode($unitPriceDetailForThisItem),
                        'ticket_code' => $internalBookingId . '-' . strtoupper(substr($dbUnitId,0,5)) . '-' . ($i+1) . rand(100,999)
                    ];
                    $bookingUnitData = array_filter($bookingUnitData, function($value) { return $value !== null || is_bool($value) || is_numeric($value); });
                    $unitFields = implode(', ', array_keys($bookingUnitData));
                    $unitPlaceholders = ':' . implode(', :', array_keys($bookingUnitData));
                    $unitSql = "INSERT INTO tbl_klook_booking_units (" . $unitFields . ") VALUES ({$unitPlaceholders})";
                    $this->query($unitSql, $bookingUnitData);
                }
            }
            $this->db->commit();
            ErrorHelper::logError("Booking {$internalBookingId} successfully created and committed.");
            return $internalBookingId;

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                 $this->db->rollBack();
                 ErrorHelper::logError("BookingModel::createKlookReservation Transaction Rolled Back due to Exception: " . $e->getMessage());
            }
            ErrorHelper::logError("BookingModel::createKlookReservation Critical Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e; 
        }
    }
      public function getBookingForKlookResponse(
        string $bookingDbIdOrUUID, 
        ProductController $productControllerInstance, // Truyền instance của ProductController
        Product $productModel, // Vẫn cần ProductModel để findWithFullDetails
        Availability $availabilityModel, // Có thể cần cho một số logic
        bool $isKlookUUID = true
    ) {
        $fieldToQuery = $isKlookUUID ? 'uuid' : 'id';
        $sql = "SELECT * FROM {$this->table} WHERE {$fieldToQuery} = :identifier";
        $stmt = $this->query($sql, [':identifier' => $bookingDbIdOrUUID]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            return null;
        }

        // 1. Lấy dữ liệu Product thô đầy đủ
        $rawProductData = $productModel->findWithFullDetails($booking['product_id']);
        $klookProductTransformed = null;
        $klookOptionTransformed = null;

        if ($rawProductData) {
            // 2. Transform Product theo chuẩn Klook (bao gồm cả options và units với pricing)
            // Giả định pricing luôn được bao gồm trong response booking
            $klookProductTransformedWithAllOptions = $productControllerInstance->transformProductForKlook($rawProductData, true);

            // 3. Lấy ra Option đã chọn từ Product đã transform
            if (isset($klookProductTransformedWithAllOptions['options']) && is_array($klookProductTransformedWithAllOptions['options'])) {
                foreach ($klookProductTransformedWithAllOptions['options'] as $transformedOpt) {
                    if ($transformedOpt['id'] === $booking['option_id']) {
                        $klookOptionTransformed = $transformedOpt;
                        // Trong response của booking, đối tượng 'option' không cần chứa lại mảng 'units'
                        // vì thông tin unit cụ thể nằm trong 'unitItems' của booking.
                        if (isset($klookOptionTransformed['units'])) {
                            unset($klookOptionTransformed['units']);
                        }
                        break;
                    }
                }
            }
            // Đối tượng 'product' trong response booking có thể không cần toàn bộ mảng 'options'.
            // Chỉ cần thông tin product chính.
            $klookProductTransformed = $klookProductTransformedWithAllOptions; // Lấy toàn bộ product đã transform
            if (isset($klookProductTransformed['options'])) {
                 // Theo Klook spec, đối tượng product lồng trong booking vẫn chứa options (Source [177] ví dụ)
                 // Tuy nhiên, có thể bạn muốn lược bớt để response gọn hơn, chỉ giữ lại option đã chọn
                 // Hoặc giữ nguyên nếu Klook mong đợi đầy đủ. Hiện tại giữ nguyên.
                 // unset($klookProductTransformed['options']);
            }
        }
        
        // 4. Tạo đối tượng Availability
        $availabilityObject = null;
        if ($booking['klook_availability_id']) {
            try {
                $productTimezoneStr = $rawProductData['time_zone'] ?? $klookProductTransformed['timeZone'] ?? 'Asia/Ho_Chi_Minh';
                $productTimezone = new \DateTimeZone($productTimezoneStr);

                $slotDateTime = new \DateTime($booking['klook_availability_id']); // Klook ID đã có TZ offset
                $slotStartDateTimeLocal = (clone $slotDateTime)->setTimezone($productTimezone);

                // Lấy duration từ option đã chọn
                $durationHours = 4; // Giá trị mặc định
                if ($klookOptionTransformed && isset($klookOptionTransformed['duration_hours_from_db'])) { // Giả sử bạn thêm trường này khi transform option
                    $durationHours = (int)$klookOptionTransformed['duration_hours_from_db'];
                } else if ($optionDetailsFromDb = $this->productOptionModel->findByProductAndOption($booking['product_id'], $booking['option_id'])) {
                    // Hoặc lấy từ DB option nếu chưa có trong $klookOptionTransformed
                     if(isset($optionDetailsFromDb['duration_hours'])) $durationHours = (int)$optionDetailsFromDb['duration_hours'];
                }

                $slotEndDateTimeLocal = (clone $slotStartDateTimeLocal)->modify("+{$durationHours} hours");
                
                $availabilityObject = [
                    'id' => $booking['klook_availability_id'],
                    'localDateTimeStart' => $slotStartDateTimeLocal->format(DATE_ATOM),
                    'localDateTimeEnd' => $slotEndDateTimeLocal->format(DATE_ATOM),
                    'allDay' => ($klookProductTransformed['availabilityType'] ?? 'START_TIME') === 'OPENING_HOURS',
                    'openingHours' => [] 
                ];
            } catch (\Exception $e) { 
                ErrorHelper::logError("Error parsing klook_availability_id '{$booking['klook_availability_id']}' for booking response: " . $booking['id'] . " - " . $e->getMessage()); 
            }
        }

        // 5. Lấy và định dạng Unit Items đã đặt
        $stmtUnits = $this->query(
            "SELECT bu.*, 
                    u.internal_name as unit_def_internal_name, u.type as unit_def_type, 
                    u.reference as unit_def_reference, u.required_contact_fields as unit_def_req_fields, 
                    u.restrictions as unit_def_restrictions, u.pax_count as unit_def_pax_count
             FROM tbl_klook_booking_units bu
             LEFT JOIN tbl_klook_units u ON bu.klook_unit_id = u.id AND u.product_id = :product_id AND u.option_id = :option_id
             WHERE bu.booking_db_id = :booking_id", 
             ['product_id' => $booking['product_id'], 'option_id' => $booking['option_id'], 'booking_id' => $booking['id']]
        );
        $dbUnitItems = $stmtUnits->fetchAll(PDO::FETCH_ASSOC);
        $responseUnitItems = [];

        if (is_array($dbUnitItems)) {
            foreach ($dbUnitItems as $dbUnitItem) {
                // Tạo đối tượng 'unit' (definition) cho mỗi unit item
                $unitDefinitionObject = [
                    'id' => $dbUnitItem['klook_unit_id'],
                    'internalName' => $dbUnitItem['unit_def_internal_name'],
                    'reference' => $dbUnitItem['unit_def_reference'],
                    'type' => $dbUnitItem['unit_def_type'],
                    'requiredContactFields' => $dbUnitItem['unit_def_req_fields'] ? json_decode($dbUnitItem['unit_def_req_fields'], true) : [],
                    'restrictions' => $dbUnitItem['unit_def_restrictions'] ? json_decode($dbUnitItem['unit_def_restrictions'], true) : null, // Giả sử restrictions lưu JSON
                    'paxCount' => (int)($dbUnitItem['unit_def_pax_count'] ?? 1),
                    // Không có pricingFrom ở đây theo spec booking response cho unit definition lồng nhau
                ];
                
                // Nếu lưu quantity > 1 trên một dòng booking_unit, bạn cần lặp ở đây
                // Hiện tại, createKlookReservation đang lưu mỗi vé là 1 dòng, nên quantity ở đây là 1
                $responseUnitItems[] = [
                    'uuid' => $dbUnitItem['unit_item_uuid'], // UUID của dòng unit item này
                    'resellerReference' => $dbUnitItem['reseller_reference_item'] ?? null,
                    'supplierReference' => $dbUnitItem['supplier_reference_item'] ?? ($dbUnitItem['ticket_code'] ?? null),
                    'unitId' => $dbUnitItem['klook_unit_id'],
                    'unit' => $unitDefinitionObject,
                    'status' => strtoupper($dbUnitItem['status'] ?? $booking['status']), // Lấy status của unit item, fallback về booking status
                    'utcRedeemedAt' => $dbUnitItem['utc_redeemed_at'] ? (new \DateTime($dbUnitItem['utc_redeemed_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM) : null,
                    'contact' => $dbUnitItem['contact_details_item'] ? json_decode($dbUnitItem['contact_details_item'], true) : null,
                    'ticket' => $dbUnitItem['ticket_details_item'] ? json_decode($dbUnitItem['ticket_details_item'], true) : null,
                ];
            }
        }
        
        // 6. Ánh xạ và định dạng các trường còn lại của Booking
        $klookStatus = strtoupper($booking['status'] ?? 'ON_HOLD'); 
        if ($booking['status'] === 'pending') $klookStatus = 'ON_HOLD'; // Đảm bảo ánh xạ đúng

        $cancellable = false; // Mặc định
        if($klookOptionTransformed) { // Cần $klookOptionTransformed để lấy thông tin cutoff
             $cancellable = $this->calculateCancellableStatus($booking, $klookOptionTransformed, $productDetails['time_zone'] ?? 'Asia/Ho_Chi_Minh');
        }


        return [
            'id' => $booking['id'], 
            'uuid' => $booking['uuid'], 
            'testMode' => (bool)($booking['test_mode'] ?? false),
            'resellerReference' => $booking['reseller_reference'] ?? null, 
            'supplierReference' => $booking['supplier_reference'] ?? $booking['id'],
            'status' => $klookStatus,
            'utcCreatedAt' => $booking['utc_created_at'] ? (new \DateTime($booking['utc_created_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM) : null,
            'utcUpdatedAt' => $booking['utc_updated_at'] ? (new \DateTime($booking['utc_updated_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM) : null,
            'utcExpiresAt' => $booking['utc_expires_at'] ? (new \DateTime($booking['utc_expires_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM) : null,
            'utcRedeemedAt' => $booking['utc_redeemed_at'] ? (new \DateTime($booking['utc_redeemed_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM) : null,
            'utcConfirmedAt' => $booking['utc_confirmed_at'] ? (new \DateTime($booking['utc_confirmed_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM) : null,
            'productId' => $booking['product_id'], 
            'product' => $klookProductTransformed, // Product đã được transform
            'optionId' => $booking['option_id'], 
            'option' => $klookOptionTransformed,   // Option đã được transform
            'cancellable' => $cancellable, 
            'cancellation' => $booking['cancellation_details'] ? json_decode($booking['cancellation_details'], true) : null,
            'freesale' => (bool)($booking['freesale_booking'] ?? false),
            'availabilityId' => $booking['klook_availability_id'] ?? null, 
            'availability' => $availabilityObject,
            'contact' => $booking['contact_details'] ? json_decode($booking['contact_details'], true) : null,
            'notes' => $booking['notes'] ?? null,
            'deliveryMethods' => $booking['delivery_methods_snapshot'] ? json_decode($booking['delivery_methods_snapshot'], true) : ($klookProductTransformed['deliveryMethods'] ?? []),
            'voucher' => $booking['voucher_details'] ? json_decode($booking['voucher_details'], true) : null,
            'unitItems' => $responseUnitItems,
            'pricing' => $booking['booking_pricing_details'] ? json_decode($booking['booking_pricing_details'], true) : null,
        ];
    }
    public function getBookingByKlookUUID(string $uuid) {
        $sql = "SELECT * FROM {$this->table} WHERE uuid = ?";
        $stmt = $this->query($sql, [$uuid]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function formatBookingForKlookResponse(array $booking, Product $productModel, Availability $availabilityModel) {
        if (!$booking) return null;

        $productController = new ProductController(); // Tạm thời, không lý tưởng
        $rawProductData = $productModel->findWithFullDetails($booking['product_id']);
        $klookProductTransformed = null; $klookOptionTransformed = null;

        if($rawProductData){
            $klookProductTransformed = $productController->transformProductForKlook($rawProductData, true);
            if (isset($klookProductTransformed['options']) && is_array($klookProductTransformed['options'])) {
                foreach ($klookProductTransformed['options'] as $opt) {
                    if ($opt['id'] === $booking['option_id']) {
                        $klookOptionTransformed = $opt;
                        if(isset($klookOptionTransformed['units'])) unset($klookOptionTransformed['units']);
                        break;
                    }
                }
            }
            if(isset($klookProductTransformed['options'])) unset($klookProductTransformed['options']);
        }
        
        $availabilityObject = null;
        if ($booking['klook_availability_id']) { /* ... (logic tạo $availabilityObject như cũ) ... */
            try {
                $slotDateTime = new \DateTime($booking['klook_availability_id']);
                $durationHours = $klookOptionTransformed['duration_hours'] ?? ($rawProductData['options'][0]['duration_hours'] ?? 4); // Cần cách lấy duration tốt hơn
                $slotEndDateTime = (clone $slotDateTime)->modify("+{$durationHours} hours");
                $availabilityObject = [
                    'id' => $booking['klook_availability_id'],
                    'localDateTimeStart' => $slotDateTime->format(DATE_ATOM),
                    'localDateTimeEnd' => $slotEndDateTime->format(DATE_ATOM),
                    'allDay' => ($klookProductTransformed['availabilityType'] ?? 'START_TIME') === 'OPENING_HOURS',
                    'openingHours' => [] 
                ];
            } catch (\Exception $e) { ErrorHelper::logError("Error parsing availabilityId for booking response: " . $booking['id']); }
        }

        $stmtUnits = $this->query(
            "SELECT bu.*, u.internal_name as unit_def_internal_name, u.type as unit_def_type, 
                    u.reference as unit_def_reference, u.required_contact_fields as unit_def_req_fields, 
                    u.restrictions as unit_def_restrictions, u.pax_count as unit_def_pax_count
             FROM tbl_klook_booking_units bu
             LEFT JOIN tbl_klook_units u ON bu.unit_id = u.id AND u.product_id = ? AND u.option_id = ?
             WHERE bu.booking_id = ?", 
             [$booking['product_id'], $booking['option_id'], $booking['id']]
        );
        $dbUnitItems = $stmtUnits->fetchAll(PDO::FETCH_ASSOC);
        $responseUnitItems = [];
        // ... (logic tạo $responseUnitItems với 'unit' object lồng bên trong, như đã làm ở lần trước) ...
        if (is_array($dbUnitItems)) {
            foreach ($dbUnitItems as $dbUnitItem) {
                $unitDefinitionObject = [ /* ... */]; // (Như code trước)
                $responseUnitItems[] = [ /* ... */];
            }
        }
        
        $klookStatus = strtoupper($booking['status'] ?? 'ON_HOLD'); 

        return [ /* ... (toàn bộ các trường của booking response object như đã làm lần trước) ... */ 
             'id' => $booking['id'], 'uuid' => $booking['uuid'], 'testMode' => (bool)($booking['test_mode'] ?? false),
            'resellerReference' => $booking['reseller_reference'] ?? null, 'supplierReference' => $booking['supplier_reference'] ?? $booking['id'],
            'status' => $klookStatus,
            'utcCreatedAt' => $booking['utc_created_at'] ? (new \DateTime($booking['utc_created_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM) : null,
            'utcUpdatedAt' => $booking['utc_updated_at'] ? (new \DateTime($booking['utc_updated_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM) : null,
            'utcExpiresAt' => $booking['utc_expires_at'] ? (new \DateTime($booking['utc_expires_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM) : null,
            'utcRedeemedAt' => $booking['utc_redeemed_at'] ? (new \DateTime($booking['utc_redeemed_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM) : null,
            'utcConfirmedAt' => $booking['utc_confirmed_at'] ? (new \DateTime($booking['utc_confirmed_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM) : null,
            'productId' => $booking['product_id'], 'product' => $klookProductTransformed,
            'optionId' => $booking['option_id'], 'option' => $klookOptionTransformed,
            'cancellable' => $this->calculateCancellableStatus($booking, $klookOptionTransformed, $productDetails['time_zone'] ?? 'Asia/Ho_Chi_Minh'), 
            'cancellation' => $booking['cancellation_details'] ? json_decode($booking['cancellation_details'], true) : null,
            'freesale' => (bool)($booking['freesale_booking'] ?? false),
            'availabilityId' => $booking['klook_availability_id'] ?? null, 'availability' => $availabilityObject,
            'contact' => $booking['contact_details'] ? json_decode($booking['contact_details'], true) : null,
            'notes' => $booking['notes'] ?? null,
            'deliveryMethods' => $booking['delivery_methods_snapshot'] ? json_decode($booking['delivery_methods_snapshot'], true) : ($klookProductTransformed['deliveryMethods'] ?? []),
            'voucher' => $booking['voucher_details'] ? json_decode($booking['voucher_details'], true) : null,
            'unitItems' => $responseUnitItems,
            'pricing' => $booking['booking_pricing_details'] ? json_decode($booking['booking_pricing_details'], true) : null,
        ];
    }
    
    private function calculateCancellableStatus(array $booking, ?array $transformedOptionDetails, string $productTimezoneStr): bool {
        // ... (Logic tính toán cancellable như đã viết ở lần trước)
        if (strtoupper($booking['status'] ?? '') !== 'CONFIRMED' && strtoupper($booking['status'] ?? '') !== 'ON_HOLD') return false;
        if (!$transformedOptionDetails || !isset($transformedOptionDetails['cancellationCutoffAmount']) || !isset($transformedOptionDetails['cancellationCutoffUnit'])) return true; 
        try {
            $productTimezone = new \DateTimeZone($productTimezoneStr);
            $nowInProductTimezone = new \DateTime('now', $productTimezone);
            $bookingStartTimeStr = $booking['klook_availability_id'] ?? ($booking['booking_date'] . ' ' . ($booking['start_time'] ?? '00:00:00'));
            $bookingStartTime = new \DateTime($bookingStartTimeStr); // Klook availability ID đã có timezone, hoặc booking_date + start_time là local
            if ($booking['klook_availability_id']) $bookingStartTime->setTimezone($productTimezone); // Chuyển về product timezone nếu cần
            
            $cutoffAmount = (int)$transformedOptionDetails['cancellationCutoffAmount'];
            $cutoffUnit = $transformedOptionDetails['cancellationCutoffUnit'];
            $cutoffDateTime = (clone $bookingStartTime)->modify("-{$cutoffAmount} {$cutoffUnit}");
            return $nowInProductTimezone < $cutoffDateTime;
        } catch (\Exception $e) { return false; }
    }

    // TODO: Viết các phương thức: confirmKlookBooking, cancelKlookBooking, getKlookBookingsByFilters
    public function confirmKlookBooking(string $uuid, array $confirmationData) { /* ... */ return false; }
    public function cancelKlookBooking(string $uuid, ?string $reason) { /* ... */ return false; }
    public function getKlookBookingsByFilters(array $filters) { /* ... */ return []; }

}