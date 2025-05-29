<?php
//supplier
$router->get('/supplier', 'SupplierController@index', ['AuthMiddleware']);
// Product routes
$router->get('/products', 'ProductController@index', ['AuthMiddleware']);
$router->get('/products/{id}', 'ProductController@show', ['AuthMiddleware']);
$router->post('/products', 'ProductController@create', ['AuthMiddleware', 'CapabilityMiddleware']);
$router->put('/products/{id}', 'ProductController@update', ['AuthMiddleware', 'CapabilityMiddleware']);
$router->delete('/products/{id}', 'ProductController@delete', ['AuthMiddleware', 'CapabilityMiddleware']);

// Availability routes
$router->get('/products/{id}/availability', 'AvailabilityController@index', ['AuthMiddleware']);
$router->post('/products/{id}/availability', 'AvailabilityController@create', ['AuthMiddleware', 'CapabilityMiddleware']);

// Booking routes
$router->get('/bookings', 'BookingController@index', ['AuthMiddleware']);
$router->get('/bookings/{id}', 'BookingController@show', ['AuthMiddleware']);
$router->post('/bookings', 'BookingController@create', ['AuthMiddleware', 'CapabilityMiddleware']);
$router->put('/bookings/{id}', 'BookingController@update', ['AuthMiddleware', 'CapabilityMiddleware']);
$router->delete('/bookings/{id}', 'BookingController@cancel', ['AuthMiddleware', 'CapabilityMiddleware']);

// Pickup routes
$router->get('/pickups', 'PickupController@index', ['AuthMiddleware']);
$router->post('/pickups', 'PickupController@create', ['AuthMiddleware', 'CapabilityMiddleware']);

// Health check
$router->get('/health', 'HealthController@check');