<?php
// Supplier:
$router->get('/supplier', 'SupplierController@index', ['AuthMiddleware']);
// Product routes
$router->get('/products', 'ProductController@index', ['AuthMiddleware']);
$router->get('/products/{id}', 'ProductController@show', ['AuthMiddleware']);
// New Availability Calendar endpoint (matching API specification)
$router->post('/availability/calendar', 'AvailabilityController@calendar', ['AuthMiddleware']);
$router->post('/availability', 'AvailabilityController@check', ['AuthMiddleware']);
// Booking routes
$router->get('/bookings', 'BookingController@index', ['AuthMiddleware']); // Giả sử đây là Get Bookings
$router->get('/bookings/{uuid}', 'BookingController@show', ['AuthMiddleware']); // Giả sử đây là Get Booking by UUID
$router->post('/bookings', 'BookingController@reserveBooking', ['AuthMiddleware']); // Route MỚI cho Booking Reservation
$router->post('/bookings/{uuid}/confirm', 'BookingController@confirmBooking', ['AuthMiddleware']); // Sẽ cần tạo
$router->post('/bookings/{uuid}/cancel', 'BookingController@cancelBooking', ['AuthMiddleware']);   // Sẽ cần tạo (hoặc dùng DELETE)

// Pickup routes
$router->get('/pickups', 'PickupController@index', ['AuthMiddleware']);
$router->post('/pickups', 'PickupController@create', ['AuthMiddleware', 'CapabilityMiddleware']);

// Health check
$router->get('/health', 'HealthController@check');