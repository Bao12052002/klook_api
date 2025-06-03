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

    public function calendar() {
        try {
            $data = $this->getRequestBody();

            $productId = $data['productId'] ?? null;
            $optionId = $data['optionId'] ?? null;
            $startDateStr = $data['localDateStart'] ?? null;
            $endDateStr = $data['localDateEnd'] ?? null;

            if (empty($productId)) {
                return ResponseHelper::badRequest('INVALID_PRODUCT_ID', 'productId is required.', ['productId' => $productId]);
            }
            if (empty($optionId)) {
                return ResponseHelper::badRequest('INVALID_OPTION_ID', 'optionId is required.', ['optionId' => $optionId]);
            }
            if (empty($startDateStr) || !DateTime::createFromFormat('Y-m-d', $startDateStr)) {
                return ResponseHelper::badRequest('INVALID_DATE_FORMAT', 'localDateStart is required and must be in YYYY-MM-DD format.', ['localDateStart' => $startDateStr]);
            }
            if (empty($endDateStr) || !DateTime::createFromFormat('Y-m-d', $endDateStr)) {
                return ResponseHelper::badRequest('INVALID_DATE_FORMAT', 'localDateEnd is required and must be in YYYY-MM-DD format.', ['localDateEnd' => $endDateStr]);
            }

            $productDetails = $this->productModel->find($productId);
            if (!$productDetails) {
                 return ResponseHelper::badRequest('INVALID_PRODUCT_ID', 'The productId provided does not exist.', ['productId' => $productId]);
            }

            $optionDetails = $this->productOptionModel->findByProductAndOption($productId, $optionId);
            if (!$optionDetails) {
                 return ResponseHelper::badRequest('INVALID_OPTION_ID', 'The optionId was missing or invalid for the given productId.', ['productId' => $productId, 'optionId' => $optionId]);
            }

            $availabilityMap = $this->availabilityModel->findMergedAvailability($productId, $optionId, $startDateStr, $endDateStr);

            $response = [];
            $currentDate = new \DateTime($startDateStr);
            $endDateObj = new \DateTime($endDateStr); // Đổi tên biến để tránh nhầm lẫn
            $now = new \DateTime();
            $productTimezoneStr = $productDetails['time_zone'] ?? 'Asia/Ho_Chi_Minh';
            try {
                $productTimezone = new \DateTimeZone($productTimezoneStr);
            } catch (\Exception $e) {
                $productTimezone = new \DateTimeZone('Asia/Ho_Chi_Minh'); // Fallback
            }


            while ($currentDate <= $endDateObj) {
                $dateStr = $currentDate->format('Y-m-d');
                $dayData = $availabilityMap[$dateStr] ?? null;

                $vacancies = 0;
                $capacity = 0;
                $available = false;
                $status = 'CLOSED'; 

                // Xác định thông tin cutoff
                $cutoffAmount = $optionDetails['cancellation_cutoff_amount'];
                $cutoffUnit = $optionDetails['cancellation_cutoff_unit'];
                if ($dayData && isset($dayData['cancellationCutoffAmount'])) { // Ưu tiên từ availability nếu có
                    $cutoffAmount = $dayData['cancellationCutoffAmount'];
                    $cutoffUnit = $dayData['cancellationCutoffUnit'] ?? 'hour';
                }

                // Tính cutoff datetime cho ngày hiện tại, dựa trên 00:00 của ngày tour
                $cutoffDateTimeLocal = (new \DateTime($dateStr . ' 00:00:00', $productTimezone))
                                      ->modify("-{$cutoffAmount} {$cutoffUnit}");


                if ($dayData) {
                    $excludedDatesInDayData = !empty($dayData['excluded_dates']) ? json_decode($dayData['excluded_dates'], true) : [];
                    if (in_array($dateStr, $excludedDatesInDayData) || !empty($dayData['is_blocked'])) {
                        $status = 'CLOSED';
                        $available = false;
                    } else {
                        $vacancies = (int) $dayData['available_slots'];
                        $capacity = (int) ($dayData['capacity'] ?? $vacancies);
                        $available = $vacancies > 0;

                        if ($available) {
                            // API Spec for calendar status: AVAILABLE, FREESALE, SOLD_OUT, LIMITED, CLOSED
                            // Giả sử không có FREESALE cho calendar trừ khi có logic đặc biệt
                            $status = ($capacity > 0 && ($vacancies / $capacity) < 0.5) ? 'LIMITED' : 'AVAILABLE';
                        } else {
                            $status = 'SOLD_OUT';
                        }
                        
                        if ($now > $cutoffDateTimeLocal) {
                            $available = false;
                            $status = 'CLOSED';
                        }
                    }
                } else { // Không có $dayData
                    if ($now > $cutoffDateTimeLocal) {
                        $status = 'CLOSED';
                    } else {
                        // Nếu không có cấu hình availability cụ thể cho ngày này, mặc định là CLOSED
                        // (Trừ khi có logic global khác cho phép, ví dụ freesale ở mức product)
                        $status = 'CLOSED';
                    }
                    $available = false;
                }

                $response[] = [
                    'localDate' => $dateStr,
                    'available' => $available,
                    'status' => $status,
                    'vacancies' => $vacancies,
                    'capacity' => $capacity,
                    'openingHours' => [],
                ];
                $currentDate->modify('+1 day');
            }
            ResponseHelper::success($response);
        } catch (\Exception $e) {
            ErrorHelper::logError('AvailabilityController::calendar Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            // Sử dụng mã lỗi chung cho lỗi server nội bộ
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

            if (empty($productId)) {
                return ResponseHelper::badRequest('INVALID_PRODUCT_ID', 'The productId was missing or invalid.', ['productId' => $productId]);
            }
            if (empty($optionId)) {
                return ResponseHelper::badRequest('INVALID_OPTION_ID', 'The optionId was missing or invalid.', ['optionId' => $optionId]);
            }
            // API spec: "You must pass in one of the following combinations of parameters for this endpoint: localDate, localeDateStart and localDateEnd, availabilityIds"
            if (empty($localDate) && (empty($localDateStart) || empty($localDateEnd)) && empty($availabilityIds)) {
                 return ResponseHelper::badRequest('BAD_REQUEST', 'either localDate, localDateStart/localDateEnd or availabilityIds is required');
            }
             if ((!empty($localDateStart) && empty($localDateEnd)) || (empty($localDateStart) && !empty($localDateEnd))) {
                return ResponseHelper::badRequest('BAD_REQUEST', 'Both localDateStart and localDateEnd must be provided if one is present.');
            }

            $product = $this->productModel->find($productId);
            if (!$product) {
                return ResponseHelper::badRequest('INVALID_PRODUCT_ID', 'The productId was missing or invalid', ['productId' => $productId]);
            }
            
            $productTimezoneStr = $product['time_zone'] ?? 'Asia/Ho_Chi_Minh';
            try {
                $productTimezone = new \DateTimeZone($productTimezoneStr);
            } catch (\Exception $e) {
                $productTimezone = new \DateTimeZone('Asia/Ho_Chi_Minh');
            }

            $optionDetails = $this->productOptionModel->findByProductAndOption($productId, $optionId);
            if (!$optionDetails) {
                return ResponseHelper::badRequest('INVALID_OPTION_ID', 'The optionId was missing or invalid', ['optionId' => $optionId]);
            }
            
            $queryStartDate = null;
            $queryEndDate = null;

            if (!empty($availabilityIds)) {
                $datesForRange = [];
                foreach ($availabilityIds as $idStr) {
                    try {
                        $dt = new DateTime($idStr); 
                        $datesForRange[] = $dt->format('Y-m-d');
                    } catch (\Exception $e) {
                         return ResponseHelper::badRequest('INVALID_AVAILABILITY_ID', 'Invalid availabilityId format: ' . $idStr, ['availabilityId' => $idStr]);
                    }
                }
                if (empty($datesForRange)){ // Should not happen if previous check passed
                    return ResponseHelper::badRequest('INVALID_AVAILABILITY_ID', 'No valid dates could be parsed from availabilityIds.');
                }
                $queryStartDate = min($datesForRange);
                $queryEndDate = max($datesForRange);
            } elseif ($localDate) {
                $queryStartDate = $localDate;
                $queryEndDate = $localDate;
            } elseif ($localDateStart && $localDateEnd) {
                 $startDtObj = new DateTime($localDateStart);
                 $endDtObj = new DateTime($localDateEnd);
                if ($startDtObj->diff($endDtObj)->days > 366) { 
                    return ResponseHelper::badRequest('BAD_REQUEST', 'Cannot request more than 1 year of availability.');
                }
                $queryStartDate = $localDateStart;
                $queryEndDate = $localDateEnd;
            }
            
            $availabilityMap = $this->availabilityModel->findMergedAvailability($productId, $optionId, $queryStartDate, $queryEndDate);
            $response = [];
            $startTimesOption = json_decode($optionDetails['availability_local_start_times'], true) ?? [];
            if (empty($startTimesOption)) {
                 ResponseHelper::success([]);
                 return;
            }

            $unitMap = [];
            if (!empty($unitsInput)) {
                foreach ($unitsInput as $u) {
                    if (isset($u['id']) && isset($u['quantity'])) {
                        $unitMap[$u['id']] = (int)($u['quantity']);
                    }
                }
            }

            $dateListForLoop = [];
            if ($availabilityIds) {
                foreach ($availabilityIds as $idStr) { // $idStr is an ISO datetime string
                     try { $dateListForLoop[] = new DateTime($idStr, $productTimezone); } catch(\Exception $e) {/* handled */}
                }
            } else {
                $cursor = new DateTime($queryStartDate, $productTimezone);
                $loopEndDate = new DateTime($queryEndDate, $productTimezone);
                while ($cursor <= $loopEndDate) {
                    $dateListForLoop[] = clone $cursor; 
                    $cursor->modify('+1 day');
                }
            }
            
            $nowLocal = new \DateTime('now', $productTimezone); // Current time in product's timezone

            foreach ($dateListForLoop as $dayDateTime) {
                $currentDateStr = $dayDateTime->format('Y-m-d');
                $dayAvailabilityData = $availabilityMap[$currentDateStr] ?? null;

                foreach ($startTimesOption as $timeStr) {
                    try {
                        $slotStartDateTimeLocal = new DateTime("{$currentDateStr}T{$timeStr}", $productTimezone);
                    } catch (\Exception $e) {
                        ErrorHelper::logError("Invalid time format {$timeStr} for date {$currentDateStr} in option {$optionId} for product {$productId}");
                        continue;
                    }
                    
                    $apiAvailabilityId = $slotStartDateTimeLocal->format(DATE_ATOM);

                    if ($availabilityIds && !in_array($apiAvailabilityId, $availabilityIds)) {
                        continue;
                    }

                    $available = false;
                    $status = 'CLOSED';
                    $vacancies = 0;
                    $capacityForSlot = 0;
                    $utcCutoffAt = null;
                    
                    // Determine cutoff (priority: availability record, then option default)
                    $cutoffAmount = $optionDetails['cancellation_cutoff_amount'];
                    $cutoffUnit = $optionDetails['cancellation_cutoff_unit'];
                    if ($dayAvailabilityData && isset($dayAvailabilityData['cancellationCutoffAmount']) && (int)$dayAvailabilityData['cancellationCutoffAmount'] > 0) { // Check if set and >0
                         $cutoffAmount = (int)$dayAvailabilityData['cancellationCutoffAmount'];
                         $cutoffUnit = $dayAvailabilityData['cancellationCutoffUnit'] ?: 'hour'; // Fallback unit if null
                    }

                    $cutoffDateTimeLocal = null;
                    if ($cutoffAmount !== null && $cutoffUnit !== null) {
                         try {
                            $cutoffDateTimeLocal = (clone $slotStartDateTimeLocal)->modify("-{$cutoffAmount} {$cutoffUnit}");
                            $utcCutoffAt = (clone $cutoffDateTimeLocal)->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
                         } catch (\Exception $e) {
                             ErrorHelper::logError("Error calculating cutoff for slot {$apiAvailabilityId}: " . $e->getMessage());
                         }
                    }
                    
                    if ($dayAvailabilityData) {
                        $excludedDatesInDayData = !empty($dayAvailabilityData['excluded_dates']) ? json_decode($dayAvailabilityData['excluded_dates'], true) : [];
                        if (in_array($currentDateStr, $excludedDatesInDayData) || !empty($dayAvailabilityData['is_blocked'])) {
                            $status = 'CLOSED';
                            $available = false;
                        } else {
                            $vacancies = (int)$dayAvailabilityData['available_slots'];
                            $capacityForSlot = (int)($dayAvailabilityData['capacity'] ?? $vacancies);
                            $available = $vacancies > 0;

                            if ($available) {
                                 // API Spec for check status: AVAILABLE, FREESALE, SOLD_OUT, LIMITED, CLOSED
                                $status = ($capacityForSlot > 0 && ($vacancies / $capacityForSlot) < 0.5) ? 'LIMITED' : 'AVAILABLE';
                            } else {
                                $status = 'SOLD_OUT';
                            }
                        }
                    } else { // No specific availability record for this day
                        $status = 'CLOSED'; // Default to CLOSED if no explicit availability
                        $available = false;
                    }

                    // Final check against cutoff time
                    if ($cutoffDateTimeLocal && $nowLocal > $cutoffDateTimeLocal) {
                        $available = false;
                        $status = 'CLOSED';
                    }
                    
                    if ($available && !empty($unitMap)) {
                        $totalQtyRequested = array_sum($unitMap);
                        if ($totalQtyRequested <= 0) { // Cannot book zero or negative
                            $available = false;
                            $status = 'CLOSED'; // Or a more specific error like INVALID_QUANTITY
                        } elseif ($totalQtyRequested > $vacancies) {
                            $available = false;
                            $status = 'SOLD_OUT';
                        }
                    }
                    
                    $slotAllDay = ($product['availability_type'] ?? 'START_TIME') === 'OPENING_HOURS';
                    // For START_TIME, localDateTimeEnd is usually calculated based on a duration.
                    // API doc for /availability response has localDateTimeEnd. We need a duration.
                    // Assuming a fixed duration for now or a 'duration_hours' field in $optionDetails
                    $durationHours = $optionDetails['duration_hours'] ?? 4; // Example: fallback to 4 hours
                    $slotEndDateTimeLocal = (clone $slotStartDateTimeLocal)->modify("+{$durationHours} hours");


                    $response[] = [
                        'id' => $apiAvailabilityId,
                        'localDateTimeStart' => $slotStartDateTimeLocal->format(DATE_ATOM),
                        'localDateTimeEnd' => $slotEndDateTimeLocal->format(DATE_ATOM),
                        'allDay' => $slotAllDay,
                        'available' => $available,
                        'status' => $status,
                        'vacancies' => $vacancies,
                        'capacity' => $capacityForSlot,
                        'maxUnits' => $optionDetails['max_units'] === null ? null : (int)$optionDetails['max_units'],
                        'utcCutoffAt' => $utcCutoffAt,
                        'openingHours' => [] // For START_TIME, this is usually empty. For OPENING_HOURS, it needs data.
                    ];
                }
            }
            ResponseHelper::success($response);
        } catch (\Exception $e) {
            ErrorHelper::logError('AvailabilityController::check Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'An unexpected error occurred.');
        }
    }
}