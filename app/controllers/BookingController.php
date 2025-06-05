<?php
// File: app/controllers/BookingController.php
class BookingController extends Controller {
    private $bookingModel;
    private $productModel;
    private $productOptionModel;
    private $availabilityModel;

    public function __construct() {
        $this->bookingModel = new Booking();
        $this->productModel = new Product();
        $this->productOptionModel = new ProductOption();
        $this->availabilityModel = new Availability();
    }

    /**
     * POST /bookings - Booking Reservation
     */
    public function reserveBooking() {
        try {
            $requestBody = $this->getRequestBody();
            if ($requestBody === null) return ResponseHelper::badRequest('INVALID_REQUEST_BODY', 'Request body is missing or not valid JSON.');

            $productId = $requestBody['productId'] ?? null;
            $optionId = $requestBody['optionId'] ?? null;
            $klookAvailabilityId = $requestBody['availabilityId'] ?? null;
            $unitItemsInput = $requestBody['unitItems'] ?? null;
            $bookingUUID = $requestBody['uuid'] ?? null; 
            $notes = $requestBody['notes'] ?? null;
            $expirationMinutes = isset($requestBody['expirationMinutes']) ? (int)$requestBody['expirationMinutes'] : 30;
            $requestedCurrency = $requestBody['currency'] ?? null;

            if (empty($productId)) return ResponseHelper::badRequest('INVALID_PRODUCT_ID', 'productId is required.');
            if (empty($optionId)) return ResponseHelper::badRequest('INVALID_OPTION_ID', 'optionId is required.');
            if (empty($klookAvailabilityId)) return ResponseHelper::badRequest('INVALID_AVAILABILITY_ID', 'availabilityId is required.');
            if (empty($unitItemsInput) || !is_array($unitItemsInput)) return ResponseHelper::badRequest('INVALID_UNIT_ITEMS', 'unitItems array is required and cannot be empty.');
            if ($expirationMinutes <= 0) $expirationMinutes = 30;

            $productDetails = $this->productModel->find($productId);
            if (!$productDetails) return ResponseHelper::badRequest('INVALID_PRODUCT_ID', 'Product not found.', ['productId' => $productId]);
            
            $optionDetails = $this->productOptionModel->findByProductAndOption($productId, $optionId);
            if (!$optionDetails) return ResponseHelper::badRequest('INVALID_OPTION_ID', 'Option not found for the given product.', ['productId' => $productId, 'optionId' => $optionId]);
            
            $productTimezoneStr = $productDetails['time_zone'] ?? 'Asia/Ho_Chi_Minh';
            try { $productTimezone = new \DateTimeZone($productTimezoneStr); } 
            catch (\Exception $e) { $productTimezone = new \DateTimeZone('Asia/Ho_Chi_Minh'); }

            $definedUnitsForOptionDb = $this->productModel->getOptionUnits($productId, $optionId);
            $definedUnitsMap = [];
            if(is_array($definedUnitsForOptionDb)) { foreach ($definedUnitsForOptionDb as $dbUnit) { $definedUnitsMap[$dbUnit['id']] = $dbUnit; } }

            $parsedUnitItems = []; $totalPaxRequested = 0; $unitValidationError = null;
            foreach ($unitItemsInput as $reqUnit) { /* ... (logic parse và validate unitItems như đã làm ở Bước 1) ... */
                $reqUnitId = $reqUnit['unitId'] ?? null; $reqUnitItemUuid = $reqUnit['uuid'] ?? null;
                if (!$reqUnitId || !isset($definedUnitsMap[$reqUnitId])) { $unitValidationError = ['error' => 'INVALID_UNIT_ID', 'errorMessage' => "Unit with id '{$reqUnitId}' is not valid for this option.", 'unitId' => $reqUnitId]; break; }
                $unitDetailsFromDb = $definedUnitsMap[$reqUnitId];
                if (!isset($parsedUnitItems[$reqUnitId])) { $parsedUnitItems[$reqUnitId] = ['quantity' => 0, 'details' => $unitDetailsFromDb, 'uuids' => [], 'pax_count_per_unit' => (int)($unitDetailsFromDb['pax_count'] ?? 1)];}
                $parsedUnitItems[$reqUnitId]['quantity']++; if ($reqUnitItemUuid) $parsedUnitItems[$reqUnitId]['uuids'][] = $reqUnitItemUuid;
            }
            if ($unitValidationError) return ResponseHelper::badRequest($unitValidationError['error'], $unitValidationError['errorMessage'], ['unitId' => $unitValidationError['unitId'] ?? null]);
            foreach($parsedUnitItems as $unitIdKey => &$itemData) {
                $details = $itemData['details']; $currentQuantity = $itemData['quantity'];
                 if ((isset($details['min_quantity']) && $details['min_quantity'] !== null && $currentQuantity < (int)$details['min_quantity'])) return ResponseHelper::badRequest('UNIT_QUANTITY_BELOW_MINIMUM', "Total quantity for unit '{$unitIdKey}' ({$currentQuantity}) is below minimum of {$details['min_quantity']}.", ['unitId' => $unitIdKey, 'quantity' => $currentQuantity]);
                if ((isset($details['max_quantity']) && $details['max_quantity'] !== null && $currentQuantity > (int)$details['max_quantity'])) return ResponseHelper::badRequest('UNIT_QUANTITY_EXCEEDS_MAXIMUM', "Total quantity for unit '{$unitIdKey}' ({$currentQuantity}) exceeds maximum of {$details['max_quantity']}.", ['unitId' => $unitIdKey, 'quantity' => $currentQuantity]);
                $totalPaxRequested += $currentQuantity * $itemData['pax_count_per_unit'];
            } unset($itemData);
            // TODO: Kiểm tra accompaniedBy
            if ($totalPaxRequested <= 0) return ResponseHelper::badRequest('INVALID_QUANTITY', 'Total pax requested must be greater than zero.');

            $slotStartDateTimeLocal = null;
            try { $slotStartDateTimeUTC = new \DateTime($klookAvailabilityId); $slotStartDateTimeLocal = $slotStartDateTimeUTC->setTimezone($productTimezone); $slotDateForDbQuery = $slotStartDateTimeLocal->format('Y-m-d'); $slotTimeForDbQuery = $slotStartDateTimeLocal->format('H:i'); } 
            catch (\Exception $e) { return ResponseHelper::badRequest('INVALID_AVAILABILITY_ID', 'availabilityId is not a valid ISO8601 datetime string.', ['availabilityId' => $klookAvailabilityId]); }
            $optionStartTimes = json_decode($optionDetails['availability_local_start_times'] ?? '[]', true);
            if (!is_array($optionStartTimes) || !in_array($slotTimeForDbQuery, $optionStartTimes)) return ResponseHelper::badRequest('INVALID_AVAILABILITY_ID', 'The time specified in availabilityId is not a valid start time for this option.', ['availabilityId' => $klookAvailabilityId]);
            $availabilityMapForDay = $this->availabilityModel->findMergedAvailability($productId, $optionId, $slotDateForDbQuery, $slotDateForDbQuery);
            $dayAvailData = $availabilityMapForDay[$slotDateForDbQuery] ?? null;
            $nowLocal = new \DateTime('now', $productTimezone);
            if (!$dayAvailData || !empty($dayAvailData['is_blocked'])) return ResponseHelper::badRequest('AVAILABILITY_CLOSED', 'No availability defined or day is blocked.', ['date' => $slotDateForDbQuery]);
            $excludedDates = json_decode($dayAvailData['excluded_dates'] ?? '[]', true);
            if (in_array($slotDateForDbQuery, $excludedDates)) return ResponseHelper::badRequest('AVAILABILITY_CLOSED', 'The selected date is excluded.', ['date' => $slotDateForDbQuery]);
            $currentSlotCutoffAmount = $dayAvailData['cancellationCutoffAmount'] ?? $optionDetails['cancellation_cutoff_amount'];
            $currentSlotCutoffUnit = $dayAvailData['cancellationCutoffUnit'] ?? $optionDetails['cancellation_cutoff_unit'];
            $cutoffDateTimeLocalForSlot = null;
            if ($currentSlotCutoffAmount !== null && $currentSlotCutoffUnit !== null) { try { $cutoffDateTimeLocalForSlot = (clone $slotStartDateTimeLocal)->modify("-{$currentSlotCutoffAmount} {$currentSlotCutoffUnit}"); } catch (\Exception $e) { ErrorHelper::logError("Error calculating cutoff: " . $e->getMessage()); return ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'Error processing cutoff.'); }}
            if ($cutoffDateTimeLocalForSlot && $nowLocal > $cutoffDateTimeLocalForSlot) return ResponseHelper::badRequest('AVAILABILITY_CLOSED_CUTOFF', 'Slot is past cutoff time.', ['availabilityId' => $klookAvailabilityId]);
            $slotVacancies = (int)$dayAvailData['available_slots'];
            if ($slotVacancies < $totalPaxRequested) return ResponseHelper::badRequest('AVAILABILITY_SOLD_OUT', 'Insufficient vacancies.', ['requestedPax' => $totalPaxRequested, 'availableVacancies' => $slotVacancies]);
            
            $productAvailableCurrencies = json_decode($productDetails['available_currencies'] ?? '[]', true);
            if (!is_array($productAvailableCurrencies)) $productAvailableCurrencies = [];
            $targetCurrency = $requestedCurrency ?: ($productDetails['default_currency'] ?? ($productAvailableCurrencies[0] ?? 'USD'));
            if (!empty($productAvailableCurrencies) && !in_array($targetCurrency, $productAvailableCurrencies)) return ResponseHelper::badRequest('INVALID_CURRENCY', "Currency '{$targetCurrency}' is not supported.", ['currency' => $targetCurrency]);
            
            $bookingPricingDetails = null; $unitItemPricesForDb = [];
            $productPricingPer = $productDetails['pricing_per'] ?? 'UNIT';
            $finalCurrencyPrecision = $productDetails['currency_precision'] ?? 2;

            if ($productPricingPer === 'UNIT') { /* ... (logic tính $bookingPricingDetails và $unitItemPricesForDb như đã viết) ... */
                $totalOriginal = 0; $totalRetail = 0; $totalNet = 0; $aggregatedTaxes = [];
                foreach ($parsedUnitItems as $unitId => $itemData) {
                    $priceInfo = $this->productModel->getUnitPricingForDisplay($productId, $optionId, $unitId, $targetCurrency, $slotDateForDbQuery);
                    if (!$priceInfo) return ResponseHelper::error(500, 'PRICING_ERROR', "Could not retrieve pricing for unit '{$unitId}'.");
                    $unitItemPricesForDb[$unitId] = $priceInfo; $finalCurrencyPrecision = $priceInfo['currencyPrecision'];
                    $totalOriginal += $priceInfo['original'] * $itemData['quantity']; $totalRetail += $priceInfo['retail'] * $itemData['quantity'];
                    if ($priceInfo['net'] !== null) $totalNet += $priceInfo['net'] * $itemData['quantity'];
                    foreach (($priceInfo['includedTaxes'] ?? []) as $tax) { if (!isset($aggregatedTaxes[$tax['name']])) $aggregatedTaxes[$tax['name']] = ['name' => $tax['name'], 'retail' => 0, 'net' => 0]; $aggregatedTaxes[$tax['name']]['retail'] += ($tax['retail'] ?? 0) * $itemData['quantity']; if(isset($tax['net'])) $aggregatedTaxes[$tax['name']]['net'] += ($tax['net'] ?? 0) * $itemData['quantity'];}
                }
                $bookingPricingDetails = ['original' => $totalOriginal, 'retail' => $totalRetail, 'net' => $totalNet, 'currency' => $targetCurrency, 'currencyPrecision' => $finalCurrencyPrecision, 'includedTaxes' => array_values($aggregatedTaxes)];
            } elseif ($productPricingPer === 'BOOKING') { /* ... (logic tính $bookingPricingDetails) ... */
                 $bookingPricingDetails = $this->productModel->getBookingLevelPricingForDisplay($productId, $optionId, $targetCurrency, $slotDateForDbQuery);
                 if (!$bookingPricingDetails) return ResponseHelper::error(500, 'PRICING_ERROR', "Could not retrieve booking level pricing.");
            } else { return ResponseHelper::error(500, 'INVALID_PRICING_TYPE', "Invalid pricing_per type."); }
            
            $dbBookingId = $this->bookingModel->createKlookReservation(
                $productId, $optionId, $klookAvailabilityId, $parsedUnitItems, 
                $targetCurrency, $bookingPricingDetails, $unitItemPricesForDb,
                $bookingUUID, $notes, $expirationMinutes, $totalPaxRequested
            );

            if (!$dbBookingId) {
                return ResponseHelper::error(500, 'BOOKING_RESERVATION_FAILED', 'Failed to reserve booking. Slot hold may have failed or DB error.');
            }

            $bookingResponse = $this->bookingModel->getBookingForKlookResponse($dbBookingId,new ProductController(),   $this->productModel, $this->availabilityModel, false); // false vì dùng ID nội bộ
            if(!$bookingResponse) {
                 ErrorHelper::logError("Failed to retrieve booking {$dbBookingId} for Klook response after creation.");
                 return ResponseHelper::error(500, 'BOOKING_DATA_INCOMPLETE', 'Booking created but failed to format response.');
            }
            return ResponseHelper::json($bookingResponse, 201);

        } catch (\PDOException $pdoe) { /* ... */ ErrorHelper::logError("BookingController::reserveBooking PDOException: " . $pdoe->getMessage()); return ResponseHelper::error(500, 'DATABASE_ERROR', 'A database error occurred.'); } 
        catch (\Exception $e) { /* ... */ ErrorHelper::logError("BookingController::reserveBooking Exception: " . $e->getMessage()); 
            if($e->getCode() == 503) { // Lỗi từ hold slot
                 return ResponseHelper::error(503, 'SLOT_HOLD_FAILED', $e->getMessage());
            }
            return ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'An unexpected error occurred.'); }
    }

    public function confirmBooking($uuid) {
        try {
            $requestBody = $this->getRequestBody();
            // TODO: Validate $uuid và $requestBody (contact, resellerReference, unitItems contact)
            // TODO: Gọi $this->bookingModel->confirmKlookBooking($uuid, $requestBody);
            //       Model sẽ:
            //       1. Tìm booking bằng UUID, kiểm tra status là ON_HOLD, chưa hết hạn.
            //       2. Cập nhật status sang CONFIRMED, lưu utc_confirmed_at.
            //       3. Lưu contact details (vào tbl_klook_bookings.contact_details).
            //       4. Lưu reseller_reference (vào tbl_klook_bookings.reseller_reference).
            //       5. Cập nhật unitItems nếu có contact/reference riêng (tbl_klook_booking_units).
            //       6. Tạo và lưu thông tin voucher/ticket (tbl_klook_bookings.voucher_details, tbl_klook_booking_units.ticket_details_item).
            //       7. Commit transaction.
            // TODO: Lấy lại booking đã confirm và format response.
            return ResponseHelper::error(501, 'NOT_IMPLEMENTED', 'Booking confirmation not implemented.');
        } catch (\Exception $e) {
            ErrorHelper::logError("BookingController::confirmBooking Error for UUID {$uuid}: " . $e->getMessage());
            return ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'Failed to confirm booking.');
        }
    }

    public function cancelBooking($uuid) {
        try {
            $requestBody = $this->getRequestBody();
            $reason = $requestBody['reason'] ?? null;
            // TODO: Validate $uuid
            // TODO: Gọi $this->bookingModel->cancelKlookBooking($uuid, $reason, $this->availabilityModel);
            //       Model sẽ:
            //       1. Tìm booking, kiểm tra cancellable.
            //       2. Cập nhật status sang CANCELLED, lưu utc_cancelled_at, reason, refund status.
            //       3. Gọi $this->availabilityModel->releaseSlots(...) để trả lại chỗ.
            //       4. Commit transaction.
            // TODO: Lấy lại booking đã cancel và format response.
            return ResponseHelper::error(501, 'NOT_IMPLEMENTED', 'Booking cancellation not implemented.');
        } catch (\Exception $e) {
            ErrorHelper::logError("BookingController::cancelBooking Error for UUID {$uuid}: " . $e->getMessage());
            return ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'Failed to cancel booking.');
        }
    }

    public function show($uuid) { // Get Booking by UUID
        try {
            // TODO: Validate $uuid
            $bookingData = $this->bookingModel->getBookingForKlookResponse($uuid, $this->productModel,$this->productOptionModel, $this->availabilityModel, true); // true vì tìm bằng Klook UUID
            if (!$bookingData) {
                return ResponseHelper::notFound('BOOKING_NOT_FOUND', 'Booking with the given UUID not found.', ['uuid' => $uuid]);
            }
            return ResponseHelper::success($bookingData);
        } catch (\Exception $e) {
            ErrorHelper::logError("BookingController::show Error for UUID {$uuid}: " . $e->getMessage());
            return ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'Failed to retrieve booking.');
        }
    }

    public function index() { // Get Bookings (List)
        try {
            $params = $this->getQueryParams();
            // TODO: Xử lý các filter từ $params theo Klook spec
            //       (resellerReference, supplierReference, localDate, localDateStart/End, productId, optionId)
            // TODO: Gọi $this->bookingModel->getKlookBookingsByFilters($filters);
            //       Model sẽ trả về mảng các booking data thô.
            // TODO: Lặp qua mảng, với mỗi booking, gọi $this->bookingModel->formatBookingForKlookResponse(...)
            //       để tạo response cuối cùng.
            return ResponseHelper::error(501, 'NOT_IMPLEMENTED', 'List bookings not implemented.');
        } catch (\Exception $e) {
            ErrorHelper::logError("BookingController::index Error: " . $e->getMessage());
            return ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'Failed to retrieve bookings.');
        }
    }
}