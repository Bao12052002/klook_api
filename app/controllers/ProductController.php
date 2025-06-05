<?php
// File: app/controllers/ProductController.php

class ProductController extends Controller {
    private $productModel;

    public function __construct() {
        $this->productModel = new Product();
        // Nếu bạn có PricingModel riêng, hãy khởi tạo ở đây
        // private $pricingModel;
        // $this->pricingModel = new PricingModel();
    }

    public function index() {
        try {
            $params = $this->getQueryParams();
            $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
            $offset = isset($params['offset']) ? (int)$params['offset'] : 0;
            
            // findAllWithDetails sẽ lấy product cùng options và units của nó
            $products = $this->productModel->findAllWithDetails($limit, $offset);
            
            $klookProducts = [];
            // Giả định rằng chúng ta luôn bao gồm thông tin giá nếu có,
            // vì "Pricing (Mandatory)". Trong thực tế, bạn có thể kiểm tra header 'Octo-Capabilities'.
            $includePricing = true; // $this->hasCapability('octo/pricing');

            foreach ($products as $product) {
                $klookProducts[] = $this->transformToKlookFormat($product, $includePricing);
            }
            
            ResponseHelper::success($klookProducts);
        } catch (Exception $e) {
            ErrorHelper::logError("ProductController::index Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            // Sử dụng ResponseHelper đã cập nhật cho lỗi chung
            ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'An unexpected error occurred while fetching products.');
        }
    }
    
    public function show($id) {
        try {
            $product = $this->productModel->findWithFullDetails($id); // Lấy product cùng options và units
            
            if (!$product) {
                return ResponseHelper::notFound('PRODUCT_NOT_FOUND', 'Product not found.', ['productId' => $id]);
            }
            
            $includePricing = true; // $this->hasCapability('octo/pricing');
            $klookProduct = $this->transformToKlookFormat($product, $includePricing);
            ResponseHelper::success($klookProduct);
        } catch (Exception $e) {
            ErrorHelper::logError("ProductController::show Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'An unexpected error occurred while fetching the product.');
        }
    }
    
    /**
     * Transform product data to Klook API format
     * @param array $product Dữ liệu sản phẩm từ model (đã bao gồm options và units)
     * @param bool $includePricing Có bao gồm thông tin giá không
     * @return array
     */
    private function transformToKlookFormat($product, $includePricing = false) {
        $availableCurrencies = ($product['available_currencies'] && json_decode($product['available_currencies'], true)) 
                                ? json_decode($product['available_currencies'], true) 
                                : [];
        if (!is_array($availableCurrencies)) $availableCurrencies = [];

        $productPricingPer = $product['pricing_per'] ?? 'UNIT';

        $transformedProduct = [
            'id' => $product['id'],
            'internalName' => $product['internal_name'],
            'reference' => $product['reference'],
            'locale' => $product['locale'],
            'timeZone' => $product['time_zone'],
            'allowFreesale' => (bool)$product['allow_freesale'],
            'instantConfirmation' => (bool)$product['instant_confirmation'],
            'instantDelivery' => (bool)$product['instant_delivery'],
            'availabilityRequired' => (bool)$product['availability_required'],
            'availabilityType' => $product['availability_type'],
            'deliveryFormats' => json_decode($product['delivery_formats'], true),
            'deliveryMethods' => json_decode($product['delivery_methods'], true),
            'redemptionMethod' => $product['redemption_method'],
            'options' => $this->transformOptions(
                $product['options'] ?? [], 
                $product['id'], 
                $productPricingPer, 
                $availableCurrencies, 
                $includePricing
            )
        ];

        if ($includePricing) {
            $transformedProduct['defaultCurrency'] = $product['default_currency'] ?? 'USD';
            $transformedProduct['availableCurrencies'] = $availableCurrencies;
            $transformedProduct['pricingPer'] = $productPricingPer;

            if ($productPricingPer === 'BOOKING') {
                $pricingFromArray = [];
                if (!empty($availableCurrencies)) {
                    foreach ($availableCurrencies as $currency) {
                        // Lấy giá ở mức Product (option_id = NULL trong rule)
                        // Hoặc nếu giá BOOKING theo Option, bạn cần truyền optionId vào đây
                        // Giả định getBookingLevelPricingForDisplay(productId, optionIdIfAny, currency, date)
                        $priceInfo = $this->productModel->getBookingLevelPricingForDisplay(
                            $product['id'], 
                            null, // Giá ở mức product cho `pricingPer=BOOKING`
                            $currency, 
                            date('Y-m-d') // Ngày hiện tại cho giá "từ"
                        );
                        if ($priceInfo) {
                            $pricingFromArray[] = $priceInfo;
                        }
                    }
                }
                $transformedProduct['pricingFrom'] = $pricingFromArray;
            }
        }
        return $transformedProduct;
    }
    
    private function transformOptions($options, $productId, $productPricingPer, $productAvailableCurrencies, $includePricing) {
        $transformedOptions = [];
        if (!is_array($options)) return [];

        foreach ($options as $option) {
            $cancellationCutoffString = ($option['cancellation_cutoff_amount'] ?? '0') . ' ' . ($option['cancellation_cutoff_unit'] ?? 'hour');
            if (isset($option['cancellation_cutoff_amount']) && (int)$option['cancellation_cutoff_amount'] > 1 && ($option['cancellation_cutoff_unit'] ?? '') !== 'minute') {
                $cancellationCutoffString .= 's';
            }

            $transformedOpt = [
                'id' => $option['id'],
                'default' => (bool)($option['is_default'] ?? false),
                'internalName' => $option['internal_name'],
                'reference' => $option['reference'],
                'availabilityLocalStartTimes' => json_decode($option['availability_local_start_times'] ?? '[]', true),
                'cancellationCutoff' => $cancellationCutoffString,
                'cancellationCutoffAmount' => (int)($option['cancellation_cutoff_amount'] ?? 0),
                'cancellationCutoffUnit' => $option['cancellation_cutoff_unit'] ?? 'hour',
                'requiredContactFields' => json_decode($option['required_contact_fields'] ?? '[]', true),
                'restrictions' => [
                    'minUnits' => isset($option['min_units']) ? (int)$option['min_units'] : null,
                    'maxUnits' => isset($option['max_units']) ? (int)$option['max_units'] : null
                ],
                'units' => $this->transformUnits(
                    $option['units'] ?? [], 
                    $productId, 
                    $option['id'], 
                    $productPricingPer, 
                    $productAvailableCurrencies, 
                    $includePricing
                )
            ];
            
            // Theo spec, nếu pricingPer=BOOKING, pricingFrom nằm ở Product object.
            // Nếu bạn có logic giá BOOKING theo từng Option, nó sẽ được thêm ở đây.
            // if ($includePricing && $productPricingPer === 'BOOKING') {
            //     $optionPricingFromArray = [];
            //     if (!empty($productAvailableCurrencies)) {
            //         foreach ($productAvailableCurrencies as $currency) {
            //             $priceInfo = $this->productModel->getBookingLevelPricingForDisplay($productId, $option['id'], $currency, date('Y-m-d'));
            //             if ($priceInfo) {
            //                 $optionPricingFromArray[] = $priceInfo;
            //             }
            //         }
            //     }
            //     $transformedOpt['pricingFrom'] = $optionPricingFromArray;
            // }

            $transformedOptions[] = $transformedOpt;
        }
        return $transformedOptions;
    }
    
    private function transformUnits($units, $productId, $optionId, $productPricingPer, $productAvailableCurrencies, $includePricing) {
        $transformedUnits = [];
        if (!is_array($units)) return [];
        
        foreach ($units as $unit) {
            $restrictions = [
                'minAge' => isset($unit['min_age']) ? (int)$unit['min_age'] : null,
                'maxAge' => isset($unit['max_age']) ? (int)$unit['max_age'] : null,
                'idRequired' => (bool)($unit['id_required'] ?? false),
                'minQuantity' => isset($unit['min_quantity']) ? (int)$unit['min_quantity'] : null,
                'maxQuantity' => isset($unit['max_quantity']) ? (int)$unit['max_quantity'] : null,
                'paxCount' => (int)($unit['pax_count'] ?? 1),
                'accompaniedBy' => ($unit['accompanied_by'] && json_decode($unit['accompanied_by'], true)) ? json_decode($unit['accompanied_by'], true) : []
            ];
            
            $transformedUnit = [
                'id' => $unit['id'],
                'internalName' => $unit['internal_name'],
                'reference' => $unit['reference'],
                'type' => $unit['type'],
                'requiredContactFields' => json_decode($unit['required_contact_fields'] ?? '[]', true),
                'restrictions' => $restrictions
            ];

            if ($includePricing && $productPricingPer === 'UNIT') {
                $pricingFromArray = [];
                if (!empty($productAvailableCurrencies)) {
                    foreach ($productAvailableCurrencies as $currency) {
                        $priceInfo = $this->productModel->getUnitPricingForDisplay(
                            $productId, 
                            $optionId, 
                            $unit['id'], 
                            $currency, 
                            date('Y-m-d') // Lấy giá hiện tại cho "pricingFrom"
                        );
                        if ($priceInfo) {
                            $pricingFromArray[] = $priceInfo;
                        }
                    }
                }
                $transformedUnit['pricingFrom'] = $pricingFromArray;
            }
            $transformedUnits[] = $transformedUnit;
        }
        return $transformedUnits;
    }

    // --- Các phương thức CREATE, UPDATE, DELETE từ phiên bản gốc của bạn ---
    // LƯU Ý: Các phương thức này hiện CHƯA xử lý việc tạo/cập nhật pricing rules trong tbl_klook_pricing_rules
    public function create() {
        try {
            $data = $this->getRequestBody();
            
            // Validate cơ bản các trường của Product theo spec
            // Klook API spec không có endpoint POST /products, mà là GET. 
            // Tuy nhiên, nếu bạn dùng API này nội bộ để quản lý sản phẩm thì giữ lại.
            // Đặc tả API của Klook (OCTO) tập trung vào việc Klook GET dữ liệu từ Supplier.
            // Nếu đây là API để Klook ĐẨY sản phẩm vào bạn, thì cần xem lại spec đó.
            // Giả sử đây là API nội bộ của bạn:
            $this->validate($data, [
                'id' => 'required', // ID sản phẩm bạn tự quản lý
                'internal_name' => 'required',
                'locale' => 'required',
                'time_zone' => 'required',
                'delivery_formats' => 'required', // Nên là mảng
                'delivery_methods' => 'required', // Nên là mảng
                'options' => 'required' // Mảng các options
            ]);
            
            // Xử lý các trường JSON nếu đầu vào là mảng
            if (isset($data['delivery_formats']) && is_array($data['delivery_formats'])) {
                $data['delivery_formats'] = json_encode($data['delivery_formats']);
            }
            if (isset($data['delivery_methods']) && is_array($data['delivery_methods'])) {
                $data['delivery_methods'] = json_encode($data['delivery_methods']);
            }
            // Cần thêm default_currency, available_currencies, pricing_per nếu tạo mới
            if (!isset($data['default_currency'])) $data['default_currency'] = 'USD';
            if (!isset($data['available_currencies'])) $data['available_currencies'] = json_encode(['USD']);
            if (!isset($data['pricing_per'])) $data['pricing_per'] = 'UNIT';


            $productId = $this->productModel->createWithOptions($data); // Model này cần được cập nhật để lưu cả các trường pricing mới của product
            
            // LƯU Ý: Cần thêm logic để tạo các pricing rules trong tbl_klook_pricing_rules tại đây
            // dựa trên thông tin giá được gửi kèm trong $data['options'][...]['units'][...]['pricing_rules_data'] (ví dụ)

            $product = $this->productModel->findWithFullDetails($productId);
            $klookProduct = $this->transformToKlookFormat($product, true); // Hiển thị cả giá
            
            ResponseHelper::created($klookProduct); // Sử dụng ResponseHelper mới
        } catch (ValidationException $e) { // Nếu bạn dùng ValidationException
            ErrorHelper::logError("ProductController::create Validation Error: " . $e->getMessage());
            ResponseHelper::badRequest('VALIDATION_ERROR', $e->getMessage(), $e->getDetails());
        }
        catch (Exception $e) {
            ErrorHelper::logError("ProductController::create Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'Failed to create product.');
        }
    }
    
    public function update($id) {
        try {
            $productExists = $this->productModel->find($id);
            if (!$productExists) {
                 return ResponseHelper::notFound('PRODUCT_NOT_FOUND', 'Product not found to update.', ['productId' => $id]);
            }
            
            $data = $this->getRequestBody();
            
            if (isset($data['delivery_formats']) && is_array($data['delivery_formats'])) {
                $data['delivery_formats'] = json_encode($data['delivery_formats']);
            }
            if (isset($data['delivery_methods']) && is_array($data['delivery_methods'])) {
                $data['delivery_methods'] = json_encode($data['delivery_methods']);
            }
             if (isset($data['available_currencies']) && is_array($data['available_currencies'])) {
                $data['available_currencies'] = json_encode($data['available_currencies']);
            }
            
            $this->productModel->updateWithOptions($id, $data); // Model này cần cập nhật để lưu cả trường pricing mới của product

            // LƯU Ý: Cần thêm logic để cập nhật/tạo mới/xóa các pricing rules trong tbl_klook_pricing_rules
            // dựa trên thay đổi về giá trong $data.

            $updatedProduct = $this->productModel->findWithFullDetails($id);
            $klookProduct = $this->transformToKlookFormat($updatedProduct, true);
            ResponseHelper::success($klookProduct);
        } catch (Exception $e) {
            ErrorHelper::logError("ProductController::update Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'Failed to update product.');
        }
    }
    
    public function delete($id) {
        try {
            $product = $this->productModel->find($id);
            if (!$product) {
                return ResponseHelper::notFound('PRODUCT_NOT_FOUND', 'Product not found to delete.', ['productId' => $id]);
            }
            
            // LƯU Ý: Cần thêm logic để xóa các pricing rules liên quan trong tbl_klook_pricing_rules
            // và các bảng liên quan khác (options, units, availability) trước khi xóa product.
            // Hoặc dùng FOREIGN KEY với ON DELETE CASCADE nếu DB hỗ trợ và bạn muốn vậy.
            // $this->productModel->deleteAllPricingRulesForProduct($id); // Ví dụ
            // $this->productModel->deleteAllOptionsForProduct($id); // Ví dụ
            
            $this->productModel->delete($id); // Chỉ xóa product chính
            ResponseHelper::success(null); // Klook thường không cần message body cho 204 No Content hoặc 200 với data null
        } catch (Exception $e) {
            ErrorHelper::logError("ProductController::delete Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            ResponseHelper::error(500, 'INTERNAL_SERVER_ERROR', 'Failed to delete product.');
        }
    }

     public function transformProductForKlook(array $productData, bool $includePricing): array {
        // Lấy và chuẩn bị các thông tin cơ bản của sản phẩm
        $availableCurrencies = [];
        if (!empty($productData['available_currencies'])) {
            $decodedCurrencies = json_decode($productData['available_currencies'], true);
            if (is_array($decodedCurrencies)) {
                $availableCurrencies = $decodedCurrencies;
            }
        }

        $productPricingPer = $productData['pricing_per'] ?? 'UNIT';
        $defaultCurrency = $productData['default_currency'] ?? ($availableCurrencies[0] ?? 'USD'); // Fallback

        // Cấu trúc cơ bản của sản phẩm
        $transformedProduct = [
            'id' => $productData['id'],
            'internalName' => $productData['internal_name'],
            'reference' => $productData['reference'],
            'locale' => $productData['locale'],
            'timeZone' => $productData['time_zone'],
            'allowFreesale' => (bool)$productData['allow_freesale'],
            'instantConfirmation' => (bool)$productData['instant_confirmation'],
            'instantDelivery' => (bool)$productData['instant_delivery'],
            'availabilityRequired' => (bool)$productData['availability_required'],
            'availabilityType' => $productData['availability_type'],
            'deliveryFormats' => json_decode($productData['delivery_formats'] ?? '[]', true),
            'deliveryMethods' => json_decode($productData['delivery_methods'] ?? '[]', true),
            'redemptionMethod' => $productData['redemption_method'],
            'options' => $this->transformOptionsForKlook(
                $productData['options'] ?? [], 
                $productData['id'], 
                $productPricingPer, 
                $availableCurrencies, 
                $includePricing
            )
        ];

        // Thêm thông tin giá ở cấp độ sản phẩm nếu được yêu cầu
        if ($includePricing) {
            $transformedProduct['defaultCurrency'] = $defaultCurrency;
            $transformedProduct['availableCurrencies'] = $availableCurrencies;
            $transformedProduct['pricingPer'] = $productPricingPer;

            // Nếu pricingPer là BOOKING và giá được định nghĩa ở mức Product (không theo option cụ thể)
            if ($productPricingPer === 'BOOKING') {
                $pricingFromArray = [];
                if (!empty($availableCurrencies)) {
                    foreach ($availableCurrencies as $currency) {
                        // Giá ở mức Product (option_id = NULL, unit_id = NULL trong tbl_klook_pricing_rules)
                        $priceInfo = $this->productModel->getBookingLevelPricingForDisplay(
                            $productData['id'], 
                            null, // optionId là NULL để lấy giá chung của Product
                            $currency, 
                            date('Y-m-d') // Ngày hiện tại cho giá "từ" (indicative price)
                        );
                        if ($priceInfo) {
                            $pricingFromArray[] = $priceInfo;
                        }
                    }
                }
                $transformedProduct['pricingFrom'] = $pricingFromArray;
            }
        }
        return $transformedProduct;
    }
    
    /**
     * Transform mảng các options của sản phẩm.
     */
    public function transformOptionsForKlook(array $optionsData, string $productId, string $productPricingPer, array $productAvailableCurrencies, bool $includePricing): array {
        $transformedOptions = [];
        if (!is_array($optionsData)) return [];

        foreach ($optionsData as $option) {
            $cancellationCutoffAmount = (int)($option['cancellation_cutoff_amount'] ?? 0);
            $cancellationCutoffUnit = $option['cancellation_cutoff_unit'] ?? 'hour';
            $cancellationCutoffString = $cancellationCutoffAmount . ' ' . $cancellationCutoffUnit;
            if ($cancellationCutoffAmount > 1 && $cancellationCutoffUnit !== 'minute') {
                $cancellationCutoffString .= 's';
            }

            $transformedOpt = [
                'id' => $option['id'],
                'default' => (bool)($option['is_default'] ?? false),
                'internalName' => $option['internal_name'],
                'reference' => $option['reference'],
                'availabilityLocalStartTimes' => json_decode($option['availability_local_start_times'] ?? '[]', true),
                'cancellationCutoff' => $cancellationCutoffString,
                'cancellationCutoffAmount' => $cancellationCutoffAmount,
                'cancellationCutoffUnit' => $cancellationCutoffUnit,
                'requiredContactFields' => json_decode($option['required_contact_fields'] ?? '[]', true),
                'restrictions' => [
                    'minUnits' => isset($option['min_units']) ? (int)$option['min_units'] : null,
                    'maxUnits' => isset($option['max_units']) ? (int)$option['max_units'] : null
                ],
                'units' => $this->transformUnitsForKlook(
                    $option['units'] ?? [], 
                    $productId, 
                    $option['id'], 
                    $productPricingPer, 
                    $productAvailableCurrencies, 
                    $includePricing
                )
            ];
            
            // Logic nếu pricingPer=BOOKING và giá áp dụng theo từng Option (hiện tại spec Klook nói pricingFrom nằm ở Product)
            // if ($includePricing && $productPricingPer === 'BOOKING') {
            //    $optionPricingFromArray = [];
            //    if (!empty($productAvailableCurrencies)) {
            //        foreach ($productAvailableCurrencies as $currency) {
            //            $priceInfo = $this->productModel->getBookingLevelPricingForDisplay($productId, $option['id'], $currency, date('Y-m-d'));
            //            if ($priceInfo) {
            //                $optionPricingFromArray[] = $priceInfo;
            //            }
            //        }
            //    }
            //    $transformedOpt['pricingFrom'] = $optionPricingFromArray;
            // }
            $transformedOptions[] = $transformedOpt;
        }
        return $transformedOptions;
    }
    
    /**
     * Transform mảng các units của một option.
     */
    public function transformUnitsForKlook(array $unitsData, string $productId, string $optionId, string $productPricingPer, array $productAvailableCurrencies, bool $includePricing): array {
        $transformedUnits = [];
        if (!is_array($unitsData)) return [];
        
        foreach ($unitsData as $unit) {
            $restrictions = [
                'minAge' => isset($unit['min_age']) ? (int)$unit['min_age'] : null,
                'maxAge' => isset($unit['max_age']) ? (int)$unit['max_age'] : null,
                'idRequired' => (bool)($unit['id_required'] ?? false),
                'minQuantity' => isset($unit['min_quantity']) ? (int)$unit['min_quantity'] : null,
                'maxQuantity' => isset($unit['max_quantity']) ? (int)$unit['max_quantity'] : null,
                'paxCount' => (int)($unit['pax_count'] ?? 1),
                'accompaniedBy' => ($unit['accompanied_by'] && json_decode($unit['accompanied_by'], true)) ? json_decode($unit['accompanied_by'], true) : []
            ];
            
            $transformedUnit = [
                'id' => $unit['id'],
                'internalName' => $unit['internal_name'],
                'reference' => $unit['reference'],
                'type' => $unit['type'],
                'requiredContactFields' => json_decode($unit['required_contact_fields'] ?? '[]', true),
                'restrictions' => $restrictions
            ];

            if ($includePricing && $productPricingPer === 'UNIT') {
                $pricingFromArray = [];
                if (!empty($productAvailableCurrencies)) {
                    foreach ($productAvailableCurrencies as $currency) {
                        // Gọi hàm trong ProductModel để lấy giá cho unit này
                        $priceInfo = $this->productModel->getUnitPricingForDisplay(
                            $productId, 
                            $optionId, 
                            $unit['id'], 
                            $currency, 
                            date('Y-m-d') // Lấy giá hiện tại cho "pricingFrom"
                        );
                        if ($priceInfo) {
                            $pricingFromArray[] = $priceInfo;
                        }
                    }
                }
                $transformedUnit['pricingFrom'] = $pricingFromArray;
            }
            $transformedUnits[] = $transformedUnit;
        }
        return $transformedUnits;
    }
}