<?php

class AvailabilityController extends Controller {
    private $availabilityModel;
    private $productModel;
    private $productOptionModel;

    public function __construct() {
        $this->availabilityModel = new Availability();
        $this->productModel = new Product();
        $this->productOptionModel = new ProductOption();
    }

    // Phương thức calendar() được giữ nguyên từ lần cập nhật trước
   public function calendar() {
        try {
            $data = $this->getRequestBody();

            $productId = $data['productId'] ?? null;
            $optionId = $data['optionId'] ?? null;
            $startDateStr = $data['localDateStart'] ?? null;
            $endDateStr = $data['localDateEnd'] ?? null;
            $requestedCurrency = $data['currency'] ?? null;
            $requestedUnitsInput = $data['units'] ?? null;

            // === Basic Parameter Validation ===
            if (empty($productId)) return ResponseHelper::badRequest('INVALID_PRODUCT_ID', 'productId is required.', ['productId' => $productId]);
            if (empty($optionId)) return ResponseHelper::badRequest('INVALID_OPTION_ID', 'optionId is required.', ['optionId' => $optionId]);
            if (empty($startDateStr) || !DateTime::createFromFormat('Y-m-d', $startDateStr)) return ResponseHelper::badRequest('INVALID_DATE_FORMAT', 'localDateStart is required and must be in YYYY-MM-DD format.', ['localDateStart' => $startDateStr]);
            if (empty($endDateStr) || !DateTime::createFromFormat('Y-m-d', $endDateStr)) return ResponseHelper::badRequest('INVALID_DATE_FORMAT', 'localDateEnd is required and must be in YYYY-MM-DD format.', ['localDateEnd' => $endDateStr]);

            // === Fetch Product & Option Details ===
            $productDetails = $this->productModel->find($productId);
            if (!$productDetails) return ResponseHelper::badRequest('INVALID_PRODUCT_ID', 'The productId provided does not exist.', ['productId' => $productId]);

            $optionDetails = $this->productOptionModel->findByProductAndOption($productId, $optionId);
            if (!$optionDetails) return ResponseHelper::badRequest('INVALID_OPTION_ID', 'The optionId was missing or invalid for the given productId.', ['productId' => $productId, 'optionId' => $optionId]);

            $productAvailableCurrencies = json_decode($productDetails['available_currencies'] ?? '[]', true);
            if (empty($productAvailableCurrencies) && !$requestedCurrency) return ResponseHelper::badRequest('MISSING_CURRENCY_INFO', 'Product has no available currencies and no currency was specified in the request.');
            
            $targetCurrency = $requestedCurrency ?: ($productDetails['default_currency'] ?? ($productAvailableCurrencies[0] ?? null));
            if (!$targetCurrency || ($productAvailableCurrencies && !in_array($targetCurrency, $productAvailableCurrencies))) return ResponseHelper::badRequest('INVALID_CURRENCY', "Currency '{$targetCurrency}' is not available for this product.", ['requestedCurrency' => $requestedCurrency, 'availableCurrencies' => $productAvailableCurrencies]);

            $productPricingPer = $productDetails['pricing_per'] ?? 'UNIT';
            $definedUnitsForOption = [];
            if ($productPricingPer === 'UNIT') {
                $definedUnitsForOption = $this->productModel->getOptionUnits($productId, $optionId);
            }

            // === Unit Validation (nếu requestedUnitsInput được cung cấp) - Thực hiện một lần ===
            $totalPaxRequested = 0;
            $unitProcessingErrorForRequest = false; // Cờ chung cho lỗi unit của cả request

            if (!empty($requestedUnitsInput) && is_array($requestedUnitsInput)) {
                $definedUnitsMap = [];
                foreach ($definedUnitsForOption as $dbUnit) { $definedUnitsMap[$dbUnit['id']] = $dbUnit; }
                
                $tempValidatedUnits = []; // Dùng để kiểm tra accompaniedBy

                foreach ($requestedUnitsInput as $reqUnit) {
                    $reqUnitId = $reqUnit['id'] ?? null;
                    $reqQuantity = isset($reqUnit['quantity']) ? (int)$reqUnit['quantity'] : -1; // Đặt -1 để dễ kiểm tra

                    if (!$reqUnitId || $reqQuantity <= 0 || !isset($definedUnitsMap[$reqUnitId])) {
                        $unitProcessingErrorForRequest = true; break;
                    }
                    $unitDetailsFromDb = $definedUnitsMap[$reqUnitId];
                    if ((isset($unitDetailsFromDb['min_quantity']) && $reqQuantity < (int)$unitDetailsFromDb['min_quantity']) ||
                        (isset($unitDetailsFromDb['max_quantity']) && $reqQuantity > (int)$unitDetailsFromDb['max_quantity'])) {
                        $unitProcessingErrorForRequest = true; break;
                    }
                    $totalPaxRequested += $reqQuantity * (int)($unitDetailsFromDb['pax_count'] ?? 1);
                    $tempValidatedUnits[$reqUnitId] = ['quantity' => $reqQuantity, 'details' => $unitDetailsFromDb];
                }

                if (!$unitProcessingErrorForRequest) { // Check accompaniedBy nếu các bước trước ok
                    foreach ($tempValidatedUnits as $unitId => $unitData) {
                        $accompaniedByJson = $unitData['details']['accompanied_by'] ?? null;
                        if (!empty($accompaniedByJson)) {
                            $accompaniedByRequiredIds = json_decode($accompaniedByJson, true);
                            if (is_array($accompaniedByRequiredIds) && !empty($accompaniedByRequiredIds)) {
                                $isAccompanied = false;
                                foreach ($accompaniedByRequiredIds as $requiredAccId) {
                                    if (isset($tempValidatedUnits[$requiredAccId]) && $tempValidatedUnits[$requiredAccId]['quantity'] > 0) {
                                        $isAccompanied = true; break;
                                    }
                                }
                                if (!$isAccompanied) { $unitProcessingErrorForRequest = true; break; }
                            }
                        }
                        if($unitProcessingErrorForRequest) break;
                    }
                }
            }
            // === Kết thúc Unit Validation ===

            $availabilityMap = $this->availabilityModel->findMergedAvailability($productId, $optionId, $startDateStr, $endDateStr);
            $response = [];
            $currentDate = new \DateTime($startDateStr);
            $endDateObj = new \DateTime($endDateStr);
            $now = new \DateTime();
            $productTimezoneStr = $productDetails['time_zone'] ?? 'Asia/Ho_Chi_Minh';
            try { $productTimezone = new \DateTimeZone($productTimezoneStr); } 
            catch (\Exception $e) { $productTimezone = new \DateTimeZone('Asia/Ho_Chi_Minh'); }

            while ($currentDate <= $endDateObj) {
                $dateStr = $currentDate->format('Y-m-d');
                $dayDataFromAvailabilityTable = $availabilityMap[$dateStr] ?? null;

                $dayVacancies = 0; $dayCapacity = 0; 
                $dayIsGenerallyAvailable = false; $dayStatus = 'CLOSED';

                $cutoffAmount = $optionDetails['cancellation_cutoff_amount'];
                $cutoffUnit = $optionDetails['cancellation_cutoff_unit'];
                if ($dayDataFromAvailabilityTable && isset($dayDataFromAvailabilityTable['cancellationCutoffAmount']) && (int)$dayDataFromAvailabilityTable['cancellationCutoffAmount'] > 0) {
                    $cutoffAmount = (int)$dayDataFromAvailabilityTable['cancellationCutoffAmount'];
                    $cutoffUnit = $dayDataFromAvailabilityTable['cancellationCutoffUnit'] ?: 'hour';
                }
                $cutoffDateTimeLocal = (new \DateTime($dateStr . ' 00:00:00', $productTimezone))->modify("-{$cutoffAmount} {$cutoffUnit}");

                if ($dayDataFromAvailabilityTable) {
                    $excludedDatesInDayData = !empty($dayDataFromAvailabilityTable['excluded_dates']) ? json_decode($dayDataFromAvailabilityTable['excluded_dates'], true) : [];
                    if (in_array($dateStr, $excludedDatesInDayData) || !empty($dayDataFromAvailabilityTable['is_blocked'])) {
                        $dayStatus = 'CLOSED'; $dayIsGenerallyAvailable = false;
                    } else {
                        $dayVacancies = (int) $dayDataFromAvailabilityTable['available_slots'];
                        $dayCapacity = (int) ($dayDataFromAvailabilityTable['capacity'] ?? $dayVacancies);
                        $dayIsGenerallyAvailable = $dayVacancies > 0;
                        $dayStatus = $dayIsGenerallyAvailable ? (($dayCapacity > 0 && ($dayVacancies / $dayCapacity) < 0.5) ? 'LIMITED' : 'AVAILABLE') : 'SOLD_OUT';
                        if ($now > $cutoffDateTimeLocal) { $dayIsGenerallyAvailable = false; $dayStatus = 'CLOSED';}
                    }
                } else { 
                    if ($now > $cutoffDateTimeLocal) { $dayStatus = 'CLOSED'; } else { $dayStatus = 'CLOSED'; }
                    $dayIsGenerallyAvailable = false;
                }
                
                // Áp dụng kết quả unit validation vào trạng thái của ngày
                $finalAvailableForDay = $dayIsGenerallyAvailable;
                $finalStatusForDay = $dayStatus;

                if (!empty($requestedUnitsInput)) { // Nếu có yêu cầu unit cụ thể
                    if ($unitProcessingErrorForRequest) { // Nếu có lỗi unit chung cho cả request
                        $finalAvailableForDay = false;
                        $finalStatusForDay = 'CLOSED'; // Do unit mix không phù hợp
                    } elseif ($dayIsGenerallyAvailable) { // Units hợp lệ, ngày cũng đang available chung
                        if ($totalPaxRequested > $dayVacancies) {
                            $finalAvailableForDay = false;
                            $finalStatusForDay = 'SOLD_OUT'; // Không đủ chỗ cho số lượng yêu cầu
                        }
                        // Nếu totalPaxRequested <= dayVacancies, giữ nguyên finalAvailableForDay và finalStatusForDay từ trạng thái chung
                    } else { // Ngày không available chung, thì cũng không available cho unit request này
                         $finalAvailableForDay = false;
                         // finalStatusForDay giữ nguyên từ trạng thái chung (CLOSED hoặc SOLD_OUT)
                    }
                }

                $dailyResponseObject = [
                    'localDate' => $dateStr,
                    'available' => $finalAvailableForDay,
                    'status' => $finalStatusForDay,
                    'vacancies' => $dayVacancies, // Luôn là tổng vacancies của ngày đó
                    'capacity' => $dayCapacity,   // Luôn là tổng capacity của ngày đó
                    'openingHours' => [],
                ];

                // === Thêm thông tin Pricing (logic như cũ) ===
                if ($productPricingPer === 'UNIT') {
                    $unitPricingFromArray = [];
                    if (!empty($definedUnitsForOption)) {
                        foreach ($definedUnitsForOption as $definedUnit) {
                            $priceInfo = $this->productModel->getUnitPricingForDisplay($productId, $optionId, $definedUnit['id'], $targetCurrency, $dateStr);
                            if ($priceInfo) {
                                $priceInfo['unitId'] = $definedUnit['id'];
                                $unitPricingFromArray[] = $priceInfo;
                            }
                        }
                    }
                    $dailyResponseObject['unitPricingFrom'] = $unitPricingFromArray;

                    if (!empty($requestedUnitsInput) && is_array($requestedUnitsInput) && !$unitProcessingErrorForRequest) {
                        $totalOriginal = 0; $totalRetail = 0; $totalNet = 0; $aggregatedTaxes = [];
                        foreach($requestedUnitsInput as $reqUnit) {
                            $reqUnitId = $reqUnit['id']; $reqQuantity = (int)$reqUnit['quantity'];
                            $unitPriceInfoFound = null;
                            foreach($unitPricingFromArray as $upf) { if ($upf['unitId'] === $reqUnitId) { $unitPriceInfoFound = $upf; break; } }
                            if ($unitPriceInfoFound) {
                                $totalOriginal += $unitPriceInfoFound['original'] * $reqQuantity;
                                $totalRetail += $unitPriceInfoFound['retail'] * $reqQuantity;
                                if ($unitPriceInfoFound['net'] !== null) $totalNet += $unitPriceInfoFound['net'] * $reqQuantity;
                                foreach (($unitPriceInfoFound['includedTaxes'] ?? []) as $tax) {
                                    if (!isset($aggregatedTaxes[$tax['name']])) $aggregatedTaxes[$tax['name']] = ['name' => $tax['name'], 'retail' => 0, 'net' => 0];
                                    $aggregatedTaxes[$tax['name']]['retail'] += ($tax['retail'] ?? 0) * $reqQuantity;
                                    $aggregatedTaxes[$tax['name']]['net'] += ($tax['net'] ?? 0) * $reqQuantity;
                                }
                            }
                        }
                        if ($totalRetail > 0) {
                             $dailyResponseObject['pricingFrom'] = [
                                'original' => $totalOriginal, 'retail' => $totalRetail, 'net' => $totalNet > 0 ? $totalNet : null,
                                'currency' => $targetCurrency, 'currencyPrecision' => $unitPricingFromArray[0]['currencyPrecision'] ?? 2,
                                'includedTaxes' => array_values($aggregatedTaxes)
                            ];
                        }
                    }
                } elseif ($productPricingPer === 'BOOKING') {
                    $priceInfo = $this->productModel->getBookingLevelPricingForDisplay($productId, $optionId, $targetCurrency, $dateStr);
                    if ($priceInfo) $dailyResponseObject['pricingFrom'] = $priceInfo;
                }
                $response[] = $dailyResponseObject;
                $currentDate->modify('+1 day');
            }
            ResponseHelper::success($response);
        } catch (\Exception $e) {
            ErrorHelper::logError('AvailabilityController::calendar Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'An unexpected error occurred.');
        }
    }



  public function check() {
        try {
            $data = $this->getRequestBody();

            $productId = $data['productId'] ?? null;
            $optionId = $data['optionId'] ?? null;
            $availabilityIds = $data['availabilityIds'] ?? null;
            $unitsInput = $data['units'] ?? null;
            $localDate = $data['localDate'] ?? null;
            $localDateStart = $data['localDateStart'] ?? null;
            $localDateEnd = $data['localDateEnd'] ?? null;
            $requestedCurrency = $data['currency'] ?? null;

            // === Basic Parameter Validation ===
            if (empty($productId)) return ResponseHelper::badRequest('INVALID_PRODUCT_ID', 'The productId was missing or invalid.', ['productId' => $productId]);
            if (empty($optionId)) return ResponseHelper::badRequest('INVALID_OPTION_ID', 'The optionId was missing or invalid.', ['optionId' => $optionId]);
            if (empty($localDate) && (empty($localDateStart) || empty($localDateEnd)) && empty($availabilityIds)) return ResponseHelper::badRequest('BAD_REQUEST', 'You must provide localDate, or localDateStart and localDateEnd, or availabilityIds.');
             if ((!empty($localDateStart) && empty($localDateEnd)) || (empty($localDateStart) && !empty($localDateEnd))) return ResponseHelper::badRequest('BAD_REQUEST', 'Both localDateStart and localDateEnd must be provided if one is present.');

            // === Fetch Product & Option Details ===
            $productDetails = $this->productModel->find($productId);
            if (!$productDetails) return ResponseHelper::badRequest('INVALID_PRODUCT_ID', 'The productId provided does not exist.', ['productId' => $productId]);
            
            $productTimezoneStr = $productDetails['time_zone'] ?? 'Asia/Ho_Chi_Minh';
            try { $productTimezone = new \DateTimeZone($productTimezoneStr); } 
            catch (\Exception $e) { $productTimezone = new \DateTimeZone('Asia/Ho_Chi_Minh'); }

            $optionDetails = $this->productOptionModel->findByProductAndOption($productId, $optionId);
            if (!$optionDetails) return ResponseHelper::badRequest('INVALID_OPTION_ID', 'The optionId was missing or invalid for the given productId.', ['productId' => $productId, 'optionId' => $optionId]);
            
            $productAvailableCurrencies = json_decode($productDetails['available_currencies'] ?? '[]', true);
            if (!is_array($productAvailableCurrencies)) $productAvailableCurrencies = [];
            if (empty($productAvailableCurrencies) && !$requestedCurrency) return ResponseHelper::badRequest('MISSING_CURRENCY_INFO', 'Product has no available currencies and no currency was specified in the request.');
            $targetCurrency = $requestedCurrency ?: ($productDetails['default_currency'] ?? ($productAvailableCurrencies[0] ?? null));
            if (!$targetCurrency || (!empty($productAvailableCurrencies) && !in_array($targetCurrency, $productAvailableCurrencies))) return ResponseHelper::badRequest('INVALID_CURRENCY', "Currency '{$targetCurrency}' is not available for this product.", ['requestedCurrency' => $requestedCurrency, 'availableCurrencies' => $productAvailableCurrencies]);
            
            $productPricingPer = $productDetails['pricing_per'] ?? 'UNIT';
            $definedUnitsForOptionResult = $this->productModel->getOptionUnits($productId, $optionId);
            $definedUnitsForOption = is_array($definedUnitsForOptionResult) ? $definedUnitsForOptionResult : [];

            // === Unit Validation (nếu unitsInput được cung cấp) ===
            $totalPaxRequested = 0;
            $unitProcessingErrorForRequest = false;
            $validatedRequestedUnitsList = []; 
            $firstUnitPrecisionForTotal = $productDetails['currency_precision'] ?? 2; 

            if (!empty($unitsInput) && is_array($unitsInput)) {
                $definedUnitsMap = [];
                foreach ($definedUnitsForOption as $dbUnit) { $definedUnitsMap[$dbUnit['id']] = $dbUnit; }
                $requestedUnitIdsForAccompaniedCheck = [];

                foreach ($unitsInput as $reqUnit) {
                    $reqUnitId = $reqUnit['id'] ?? null;
                    $reqQuantity = isset($reqUnit['quantity']) ? (int)$reqUnit['quantity'] : -1;
                    if (!$reqUnitId || $reqQuantity <= 0 || !isset($definedUnitsMap[$reqUnitId])) { $unitProcessingErrorForRequest = true; ErrorHelper::logError("Check Unit validation failed: Invalid ID or quantity. UnitID: {$reqUnitId}, Qty: {$reqQuantity}, Prod:{$productId}, Opt:{$optionId}"); break; }
                    $unitDetailsFromDb = $definedUnitsMap[$reqUnitId];
                    if ((isset($unitDetailsFromDb['min_quantity']) && $unitDetailsFromDb['min_quantity'] !== null && $reqQuantity < (int)$unitDetailsFromDb['min_quantity']) ||
                        (isset($unitDetailsFromDb['max_quantity']) && $unitDetailsFromDb['max_quantity'] !== null && $reqQuantity > (int)$unitDetailsFromDb['max_quantity'])) {
                        $unitProcessingErrorForRequest = true; ErrorHelper::logError("Check Unit validation failed: Min/Max quantity. UnitID: {$reqUnitId}, Qty: {$reqQuantity}, Min: {$unitDetailsFromDb['min_quantity']}, Max: {$unitDetailsFromDb['max_quantity']}"); break;
                    }
                    $totalPaxRequested += $reqQuantity * (int)($unitDetailsFromDb['pax_count'] ?? 1);
                    $validatedRequestedUnitsList[] = ['id' => $reqUnitId, 'quantity' => $reqQuantity, 'details' => $unitDetailsFromDb];
                    $requestedUnitIdsForAccompaniedCheck[] = $reqUnitId;
                }
                if (!$unitProcessingErrorForRequest) {
                    foreach ($validatedRequestedUnitsList as $vru) {
                         $accompaniedByJson = $vru['details']['accompanied_by'] ?? null;
                        if (!empty($accompaniedByJson)) {
                            $accompaniedByRequiredIds = json_decode($accompaniedByJson, true);
                            if (is_array($accompaniedByRequiredIds) && !empty($accompaniedByRequiredIds)) {
                                $isAccompanied = false;
                                foreach ($accompaniedByRequiredIds as $requiredAccId) {
                                    if (in_array($requiredAccId, $requestedUnitIdsForAccompaniedCheck)) {
                                        foreach($validatedRequestedUnitsList as $potentialAccompanyingUnit) {
                                            if ($potentialAccompanyingUnit['id'] === $requiredAccId && $potentialAccompanyingUnit['quantity'] > 0) { $isAccompanied = true; break; }
                                        }
                                    }
                                    if ($isAccompanied) break;
                                }
                                if (!$isAccompanied) { $unitProcessingErrorForRequest = true; ErrorHelper::logError("Check Unit validation failed: AccompaniedBy. UnitID: {$vru['id']}"); break; }
                            }
                        }
                        if($unitProcessingErrorForRequest) break;
                    }
                }
            }
            // === Kết thúc Unit Validation ===

            $queryStartDate = null; $queryEndDate = null;
            if (!empty($availabilityIds)) { /* ... (logic xác định queryStartDate/EndDate từ availabilityIds) ... */ 
                $datesForRange = []; foreach ($availabilityIds as $idStr) { try { $dt = new DateTime($idStr); $datesForRange[] = $dt->format('Y-m-d'); } catch (\Exception $e) { return ResponseHelper::badRequest('INVALID_AVAILABILITY_ID', 'Invalid availabilityId format: ' . $idStr, ['availabilityId' => $idStr]); }} if (empty($datesForRange)){ return ResponseHelper::badRequest('INVALID_AVAILABILITY_ID', 'No valid dates could be parsed from availabilityIds.');} $queryStartDate = min($datesForRange); $queryEndDate = max($datesForRange);
            } elseif ($localDate) { /* ... (logic cho localDate) ... */
                if (!DateTime::createFromFormat('Y-m-d', $localDate)) { return ResponseHelper::badRequest('INVALID_DATE_FORMAT', 'localDate must be in YYYY-MM-DD format.', ['localDate' => $localDate]); } $queryStartDate = $localDate; $queryEndDate = $localDate;
            } elseif ($localDateStart && $localDateEnd) { /* ... (logic cho localDateStart/End) ... */
                if (!DateTime::createFromFormat('Y-m-d', $localDateStart)) { return ResponseHelper::badRequest('INVALID_DATE_FORMAT', 'localDateStart must be in YYYY-MM-DD format.', ['localDateStart' => $localDateStart]); } if (!DateTime::createFromFormat('Y-m-d', $localDateEnd)) { return ResponseHelper::badRequest('INVALID_DATE_FORMAT', 'localDateEnd must be in YYYY-MM-DD format.', ['localDateEnd' => $localDateEnd]); } $startDtObj = new DateTime($localDateStart); $endDtObj = new DateTime($localDateEnd); if ($startDtObj > $endDtObj) { return ResponseHelper::badRequest('INVALID_DATE_RANGE', 'localDateStart cannot be after localDateEnd.'); } if ($startDtObj->diff($endDtObj)->days > 366) { return ResponseHelper::badRequest('BAD_REQUEST', 'Cannot request more than 1 year of availability.');} $queryStartDate = $localDateStart; $queryEndDate = $localDateEnd;
            }

            $availabilityMap = $this->availabilityModel->findMergedAvailability($productId, $optionId, $queryStartDate, $queryEndDate);
            $response = [];
            $startTimesOption = json_decode($optionDetails['availability_local_start_times'], true) ?? [];
            if (empty($startTimesOption)) { ResponseHelper::success([]); return; }

            $dateListForLoop = [];
            if ($availabilityIds) { foreach ($availabilityIds as $idStr) { try { $dateListForLoop[] = new DateTime($idStr, $productTimezone); } catch(\Exception $e) {/* validated earlier */} }
            } else { $cursor = new DateTime($queryStartDate, $productTimezone); $loopEndDate = new DateTime($queryEndDate, $productTimezone); while ($cursor <= $loopEndDate) { $dateListForLoop[] = clone $cursor; $cursor->modify('+1 day'); } }
            
            $nowLocal = new \DateTime('now', $productTimezone);

            foreach ($dateListForLoop as $dayDateTime) {
                $currentDateStr = $dayDateTime->format('Y-m-d');
                $dayAvailabilityData = $availabilityMap[$currentDateStr] ?? null;

                foreach ($startTimesOption as $timeStr) {
                    try { $slotStartDateTimeLocal = new DateTime("{$currentDateStr}T{$timeStr}", $productTimezone); } 
                    catch (\Exception $e) { ErrorHelper::logError("Invalid time format {$timeStr} for date {$currentDateStr} in option {$optionId} for product {$productId}"); continue; }
                    
                    $apiAvailabilityId = $slotStartDateTimeLocal->format(DATE_ATOM);
                    if ($availabilityIds && !in_array($apiAvailabilityId, $availabilityIds)) { continue; }

                    $slotVacancies = 0; $slotCapacity = 0; $slotIsGenerallyAvailable = false; $slotStatus = 'CLOSED'; $utcCutoffAt = null;
                    
                    $currentSlotCutoffAmount = $optionDetails['cancellation_cutoff_amount']; 
                    $currentSlotCutoffUnit = $optionDetails['cancellation_cutoff_unit'];
                    if ($dayAvailabilityData && isset($dayAvailabilityData['cancellationCutoffAmount']) && (int)$dayAvailabilityData['cancellationCutoffAmount'] > 0) { 
                        $currentSlotCutoffAmount = (int)$dayAvailabilityData['cancellationCutoffAmount']; 
                        $currentSlotCutoffUnit = $dayAvailabilityData['cancellationCutoffUnit'] ?: 'hour';
                    }
                    $cutoffDateTimeLocalForSlot = null;
                    if ($currentSlotCutoffAmount !== null && $currentSlotCutoffUnit !== null) { 
                        try { 
                            $cutoffDateTimeLocalForSlot = (clone $slotStartDateTimeLocal)->modify("-{$currentSlotCutoffAmount} {$currentSlotCutoffUnit}"); 
                            $utcCutoffAt = (clone $cutoffDateTimeLocalForSlot)->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM); 
                        } catch (\Exception $e) { ErrorHelper::logError("Error calculating cutoff for slot {$apiAvailabilityId}: " . $e->getMessage());}
                    }

                    if ($dayAvailabilityData) { 
                        $excludedDatesInDayData = !empty($dayAvailabilityData['excluded_dates']) ? json_decode($dayAvailabilityData['excluded_dates'], true) : []; 
                        if (in_array($currentDateStr, $excludedDatesInDayData) || !empty($dayAvailabilityData['is_blocked'])) { 
                            $slotStatus = 'CLOSED'; $slotIsGenerallyAvailable = false;
                        } else { 
                            $slotVacancies = (int)$dayAvailabilityData['available_slots']; 
                            $slotCapacity = (int)($dayAvailabilityData['capacity'] ?? $slotVacancies); 
                            $slotIsGenerallyAvailable = $slotVacancies > 0; 
                            $slotStatus = $slotIsGenerallyAvailable ? (($slotCapacity > 0 && ($slotVacancies / $slotCapacity) < 0.5) ? 'LIMITED' : 'AVAILABLE') : 'SOLD_OUT';
                        }
                    } else { 
                        $slotStatus = 'CLOSED'; $slotIsGenerallyAvailable = false; 
                    }
                    if ($cutoffDateTimeLocalForSlot && $nowLocal > $cutoffDateTimeLocalForSlot) { 
                        $slotIsGenerallyAvailable = false; $slotStatus = 'CLOSED'; 
                    }

                    $finalAvailableForRequest = $slotIsGenerallyAvailable; $finalStatusForRequest = $slotStatus;
                    if (!empty($unitsInput)) {
                        if ($unitProcessingErrorForRequest) { $finalAvailableForRequest = false; $finalStatusForRequest = 'CLOSED'; } 
                        elseif ($slotIsGenerallyAvailable) { 
                            if ($totalPaxRequested <= 0) { $finalAvailableForRequest = false; $finalStatusForRequest = 'CLOSED'; }
                            elseif ($totalPaxRequested > $slotVacancies) { $finalAvailableForRequest = false; $finalStatusForRequest = 'SOLD_OUT'; }
                        } else { $finalAvailableForRequest = false; }
                    }
                    
                    $slotAllDay = ($productDetails['availability_type'] ?? 'START_TIME') === 'OPENING_HOURS';
                    $durationHours = $optionDetails['duration_hours'] ?? 4; 
                    $slotEndDateTimeLocal = (clone $slotStartDateTimeLocal)->modify("+{$durationHours} hours");

                    $slotResponseObject = [
                        'id' => $apiAvailabilityId, 'localDateTimeStart' => $slotStartDateTimeLocal->format(DATE_ATOM),
                        'localDateTimeEnd' => $slotEndDateTimeLocal->format(DATE_ATOM), 'allDay' => $slotAllDay,
                        'available' => $finalAvailableForRequest, 'status' => $finalStatusForRequest,
                        'vacancies' => $slotVacancies, 'capacity' => $slotCapacity,
                        'maxUnits' => $optionDetails['max_units'] === null ? null : (int)$optionDetails['max_units'],
                        'utcCutoffAt' => $utcCutoffAt, 'openingHours' => []
                    ];

                    // === Thêm thông tin Pricing cho check() (unitPricing và pricing) ===
                    $slotDateForPricing = $slotStartDateTimeLocal->format('Y-m-d'); 

                    if ($productPricingPer === 'UNIT') {
                        $unitPricingArray = [];
                        if (!empty($definedUnitsForOption)) {
                            foreach ($definedUnitsForOption as $definedUnit) {
                                $priceInfo = $this->productModel->getUnitPricingForDisplay($productId, $optionId, $definedUnit['id'], $targetCurrency, $slotDateForPricing);
                                if ($priceInfo) { $priceInfo['unitId'] = $definedUnit['id']; $unitPricingArray[] = $priceInfo; }
                            }
                        }
                        $slotResponseObject['unitPricing'] = $unitPricingArray;

                        if (!empty($unitsInput) && is_array($unitsInput) && !$unitProcessingErrorForRequest) {
                            $totalOriginalForPricing = 0; $totalRetailForPricing = 0; $totalNetForPricing = 0; 
                            $aggregatedTaxesForPricing = [];
                            $currentSlotCurrencyPrecision = $firstUnitPrecisionForTotal;
                            if (!empty($unitPricingArray) && isset($unitPricingArray[0]['currencyPrecision'])) { $currentSlotCurrencyPrecision = $unitPricingArray[0]['currencyPrecision'];}

                            foreach($validatedRequestedUnitsList as $valUnit) {
                                $reqUnitId = $valUnit['id']; $reqQuantity = $valUnit['quantity'];
                                $unitPriceInfoFound = null;
                                foreach($unitPricingArray as $up) { if ($up['unitId'] === $reqUnitId) { $unitPriceInfoFound = $up; break; } }
                                if ($unitPriceInfoFound) {
                                    $totalOriginalForPricing += $unitPriceInfoFound['original'] * $reqQuantity;
                                    $totalRetailForPricing += $unitPriceInfoFound['retail'] * $reqQuantity;
                                    if ($unitPriceInfoFound['net'] !== null) $totalNetForPricing += $unitPriceInfoFound['net'] * $reqQuantity;
                                    $currentSlotCurrencyPrecision = $unitPriceInfoFound['currencyPrecision']; 
                                    foreach (($unitPriceInfoFound['includedTaxes'] ?? []) as $tax) {
                                        if (!isset($aggregatedTaxesForPricing[$tax['name']])) $aggregatedTaxesForPricing[$tax['name']] = ['name' => $tax['name'], 'retail' => 0, 'net' => 0];
                                        $aggregatedTaxesForPricing[$tax['name']]['retail'] += ($tax['retail'] ?? 0) * $reqQuantity;
                                        if(isset($tax['net'])) $aggregatedTaxesForPricing[$tax['name']]['net'] += ($tax['net'] ?? 0) * $reqQuantity;
                                    }
                                }
                            }
                            if (!empty($validatedRequestedUnitsList)) {
                                 $slotResponseObject['pricing'] = [
                                    'original' => $totalOriginalForPricing, 'retail' => $totalRetailForPricing, 
                                    'net' => $totalNetForPricing, 'currency' => $targetCurrency, 
                                    'currencyPrecision' => $currentSlotCurrencyPrecision,
                                    'includedTaxes' => array_values($aggregatedTaxesForPricing)
                                ];
                            }
                        }
                    } elseif ($productPricingPer === 'BOOKING') {
                        $priceInfo = $this->productModel->getBookingLevelPricingForDisplay($productId, $optionId, $targetCurrency, $slotDateForPricing);
                        if ($priceInfo) { $slotResponseObject['pricing'] = $priceInfo; }
                    }
                    $response[] = $slotResponseObject;
                }
            }
            ResponseHelper::success($response);
        } catch (\Exception $e) {
            ErrorHelper::logError('AvailabilityController::check Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'An unexpected error occurred.');
        }
    }
}