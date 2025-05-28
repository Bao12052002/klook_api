<?php
class BookingController extends Controller {
    private $bookingModel;
    
    public function __construct() {
        $this->bookingModel = new Booking();
    }
    
    public function index() {
        try {
            $params = $this->getQueryParams();
            $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
            $offset = isset($params['offset']) ? (int)$params['offset'] : 0;
            
            $bookings = $this->bookingModel->findAll($limit, $offset);
            
            ResponseHelper::success($bookings);
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function show($id) {
        try {
            $booking = $this->bookingModel->find($id);
            
            if (!$booking) {
                throw new NotFoundException('Booking not found');
            }
            
            ResponseHelper::success($booking);
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function create() {
        try {
            $data = $this->getRequestBody();
            
            $this->validate($data, [
                'product_id' => 'required',
                'customer_name' => 'required',
                'customer_email' => 'required',
                'booking_date' => 'required'
            ]);
            
            // Add created timestamp
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['status'] = 'pending';
            
            $bookingId = $this->bookingModel->create($data);
            $booking = $this->bookingModel->find($bookingId);
            
            ResponseHelper::created($booking, 'Booking created successfully');
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function update($id) {
        try {
            $booking = $this->bookingModel->find($id);
            if (!$booking) {
                throw new NotFoundException('Booking not found');
            }
            
            $data = $this->getRequestBody();
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            $this->bookingModel->update($id, $data);
            
            $updatedBooking = $this->bookingModel->find($id);
            ResponseHelper::success($updatedBooking, 'Booking updated successfully');
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function cancel($id) {
        try {
            $booking = $this->bookingModel->find($id);
            if (!$booking) {
                throw new NotFoundException('Booking not found');
            }
            
            $this->bookingModel->update($id, [
                'status' => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            ResponseHelper::success(null, 'Booking cancelled successfully');
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
}