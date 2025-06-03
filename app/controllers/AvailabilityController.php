<?php

class AvailabilityController extends Controller {
    private $availabilityModel;
    private $productModel;
    private $ProductOptionModel;
    
    public function __construct() {
        $this->availabilityModel = new Availability();
        $this->productModel = new Product();
        $this->ProductOptionModel = new ProductOption();
    }
    
    public function calendar() {
        try {
            $data = $this->getRequestBody();

            $this->validate($data, [
                'productId' => 'required',
                'optionId' => 'required',
                'localDateStart' => 'required|date',
                'localDateEnd' => 'required|date',
            ]);

            $productId = $data['productId'];
            $optionId = $data['optionId'];
            $startDate = $data['localDateStart'];
            $endDate = $data['localDateEnd'];

            $availability = $this->availabilityModel->findMergedAvailability($productId, $optionId, $startDate, $endDate);


            $response = [];
            $date = new \DateTime($startDate);
            $end = new \DateTime($endDate);

            while ($date <= $end) {
                    $dateStr = $date->format('Y-m-d');
                    $dayData = $availability[$dateStr] ?? null;
                    foreach ($availability as $item) {
                        if ($item['date'] === $dateStr) {
                            $dayData = $item;
                            break;
                        }
                    }

                    if ($dayData) {
                        $vacancies = (int) $dayData['available_slots'];
                        $capacity = (int) ($dayData['capacity'] ?? $vacancies);
                        $available = $vacancies > 0;

                        $status = $available
                            ? (($vacancies / $capacity) < 0.5 ? 'LIMITED' : 'AVAILABLE')
                            : 'SOLD_OUT';

                        // Kiểm tra cutoff
                        $cutoffAmount = $dayData['cancellationCutoffAmount'] ?? 0;
                        $cutoffUnit = $dayData['cancellationCutoffUnit'] ?? 'hour';
                        $cutoffDateTime = (new \DateTime($dateStr . ' 00:00:00'))->modify("-$cutoffAmount $cutoffUnit");
                        $now = new \DateTime();

                        if ($now > $cutoffDateTime) {
                            $available = false;
                            $status = 'CLOSED';
                        }
                    } else {
                        $vacancies = 0;
                        $capacity = 0;
                        $available = false;
                        $status = 'CLOSED';
                    }

                    $response[] = [
                        'localDate' => $dateStr,
                        'available' => $available,
                        'status' => $status,
                        'vacancies' => $vacancies,
                        'capacity' => $capacity,
                        'openingHours' => [],
                    ];

                    $date->modify('+1 day');
                }

            ResponseHelper::success($response);

            } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }      
    }

    
    // Keep the old methods for backward compatibility if needed
    public function index($productId) {
        try {
            // Verify product exists
            $product = $this->productModel->find($productId);
            if (!$product) {
                throw new NotFoundException('Product not found');
            }
            
            $params = $this->getQueryParams();
            $startDate = $params['start_date'] ?? date('Y-m-d');
            $endDate = $params['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
            
            $availability = $this->availabilityModel->findByProductAndDateRange($productId, $optionId, $startDate, $endDate);
            
            ResponseHelper::success($availability);
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function create($productId) {
        try {
            // Verify product exists
            $product = $this->productModel->find($productId);
            if (!$product) {
                throw new NotFoundException('Product not found');
            }
            
            $data = $this->getRequestBody();
            
            $this->validate($data, [
                'date' => 'required',
                'available_slots' => 'required'
            ]);
            
            $data['product_id'] = $productId;
            $availabilityId = $this->availabilityModel->updateAvailability(
                $productId, 
                $data['date'], 
                $data['available_slots']
            );
            
            $availability = $this->availabilityModel->findByProductAndDate($productId, $data['date']);
            
            ResponseHelper::success($availability, 'Availability updated successfully');
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    public function check()
    {
        try {
            $data = $this->getRequestBody();

            // Validate chung
            $this->validate($data, [
                'productId' => 'required',
                'optionId' => 'required'
            ]);

            $productId = $data['productId'];
            $optionId = $data['optionId'];
            $availabilityIds = $data['availabilityIds'] ?? null;
            $units = $data['units'] ?? null;

            $startDate = $data['localDateStart'] ?? null;
            $endDate = $data['localDateEnd'] ?? null;

            $now = new \DateTime();
            $timezone = new \DateTimeZone('Asia/Ho_Chi_Minh');

            // Check product & option
            $product = $this->productModel->find($productId);
            if (!$product) {
                return ResponseHelper::jsonError(400, 'INVALID_PRODUCT_ID', 'The productId was missing or invalid', ['productId' => $productId]);
            }

            $option = $this->ProductOptionModel->findByProductAndOption($productId, $optionId);
            if (!$option) {
                return ResponseHelper::jsonError(400, 'INVALID_OPTION_ID', 'The optionId was missing or invalid', ['optionId' => $optionId]);
            }
            
            // Determine mode: by availabilityIds or date range
            $availabilityMap = [];
            if (!empty($availabilityIds)) {
                // Extract unique dates from IDs
                $dates = [];
                foreach ($availabilityIds as $id) {
                    $dt = new DateTime($id);
                    $dates[] = $dt->format('Y-m-d');
                }
                $min = min($dates);
                $max = max($dates);
                $availabilityMap = $this->availabilityModel->findMergedAvailability($productId, $optionId, $min, $max);
            } elseif ($startDate && $endDate) {
                $start = new DateTime($startDate);
                $end = new DateTime($endDate);
                if ($start->diff($end)->days > 366) {
                    return ResponseHelper::jsonError(400, 'BAD_REQUEST', 'cannot request more than 1 year of availability');
                }
                $availabilityMap = $this->availabilityModel->findMergedAvailability($productId, $optionId, $startDate, $endDate);
            } else {
                return ResponseHelper::jsonError(400, 'BAD_REQUEST', 'Missing localDateStart/localDateEnd or availabilityIds');
            }

            $response = [];
            $startTimes = json_decode($option['availability_local_start_times'], true) ?? [];
            $unitMap = [];
            foreach ($units ?? [] as $u) {
                $unitMap[$u['id']] = (int)($u['quantity'] ?? 0);
            }

            // Generate date list
            $dateList = [];
            if ($availabilityIds) {
                foreach ($availabilityIds as $id) {
                    $dateList[] = new DateTime($id, $timezone);
                }
            } else {
                $cursor = new DateTime($startDate);
                $end = new DateTime($endDate);
                while ($cursor <= $end) {
                    $dateList[] = clone $cursor;
                    $cursor->modify('+1 day');
                }
            }

            foreach ($dateList as $day) {
    $dateStr = $day->format('Y-m-d');
    $dayData = $availabilityMap[$dateStr] ?? null;

    foreach ($startTimes as $time) {
        $startDT = new DateTime("{$dateStr}T{$time}:00", $timezone);
        $availabilityId = $startDT->format(DATE_ATOM);

        // Nếu có availabilityIds thì phải khớp tuyệt đối slot
        if ($availabilityIds && !in_array($availabilityId, $availabilityIds)) {
            continue; // bỏ qua slot không khớp ID
        }

        $available = false;
        $status = 'CLOSED';
        $vacancies = 0;
        $capacity = 0;

        $endDT = (clone $startDT)->modify('+4 hours');
        $cutoffAmount = $dayData['cancellationCutoffAmount'] ?? 0;
        $cutoffUnit = $dayData['cancellationCutoffUnit'] ?? 'hour';
        $cutoffDT = (clone $startDT)->modify("-{$cutoffAmount} {$cutoffUnit}");
        $cutoffUtc = (clone $cutoffDT)->setTimezone(new DateTimeZone('UTC'));

        if ($dayData && empty($dayData['excluded_dates']) || !in_array($dateStr, json_decode($dayData['excluded_dates'] ?? '[]', true))) {
            $vacancies = (int)$dayData['available_slots'];
            $capacity = (int)$dayData['capacity'] ?? $vacancies;

            $available = $vacancies > 0;
            if ($now > $cutoffDT) {
                $available = false;
                $status = 'CLOSED';
            } else {
                $status = $available
                    ? (($vacancies / $capacity) < 0.5 ? 'LIMITED' : 'AVAILABLE')
                    : 'SOLD_OUT';
            }

            if (!empty($unitMap)) {
                $totalQty = array_sum($unitMap);
                if ($totalQty > $vacancies || $totalQty === 0) {
                    $available = false;
                    $status = 'CLOSED';
                }
            }
        }
        


        $response[] = [
            'id' => $availabilityId,
            'localDateTimeStart' => $startDT->format(DATE_ATOM),
            'localDateTimeEnd' => $endDT->format(DATE_ATOM),
            'allDay' => false,
            'available' => $available,
            'status' => $status,
            'vacancies' => $vacancies,
            'capacity' => $capacity,
            'maxUnits' => null,
            'utcCutoffAt' => $cutoffUtc->format(DATE_ATOM),
            'openingHours' => []
        ];
    }
}


            ResponseHelper::success($response);

        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }


private function jsonError($statusCode, $errorCode, $errorMessage, $extra = []) {
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'error' => $errorCode,
        'errorMessage' => $errorMessage
    ], $extra));
    exit;
}



}