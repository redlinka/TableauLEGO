<?php

class User
{
    public $user_id;
    public $email;
    public $address;
    public $first_name;
    public $last_name;
    public $phone;
    public $is_verified;

    public function __construct($user_id, $email, $address, $phone, $first_name, $last_name, $is_verified)
    {
        $this->user_id = $user_id;
        $this->email = $email;
        $this->address = $address;
        $this->phone = $phone;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->is_verified = $is_verified;
    }
}
