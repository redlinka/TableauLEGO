<?php

class Order
{
    public $id_order;
    public $user_id;
    public $image_id;
    public $size;
    public $palette_choice;
    public $price_cents;
    public $status;
    public $addresse;
    public $postal_code;
    public $city;
    public $country;
    public $phone;
    public $created_at;

    public function __construct($id_order, $user_id, $image_id, $size, $palette_choice, $price_cents, $status, $addresse, $postal_code, $city, $country, $phone, $created_at)
    {
        $this->id_order = $id_order;
        $this->user_id = $user_id;
        $this->image_id = $image_id;
        $this->size = $size;
        $this->palette_choice = $palette_choice;
        $this->price_cents = $price_cents;
        $this->status = $status;
        $this->addresse = $addresse;
        $this->postal_code = $postal_code;
        $this->city = $city;
        $this->country = $country;
        $this->phone = $phone;
        $this->created_at = $created_at;
    }
}
