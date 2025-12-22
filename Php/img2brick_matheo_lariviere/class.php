<?php
class user {
    private $id;
    private $name;
    private $mail;
    private $solde;
    private $num;

    public function __construct($id, $name, $mail, $solde, $num) {
        $this->name = $name;
        $this->mail = $mail;
        $this->id = $id;
        if ($solde == null){
            $this->solde = 0;
        }else{
            $this->solde = $solde;
        }
        
        $this->num = $num;

    }

    public function get_name() {
        return $this->name;
    }

    public function get_mail() {
        return $this->mail;
    }

    public function get_id() {
        return $this->id;
    }

    public function get_solde() {
        return $this->solde;
    }

    public function get_num() {
        return $this->num;
    }

}

class image {
    private $id;
    private $img;
    private $date;
    private $pavage;
    private $price;

    public function __construct($id, $img, $date, $pavage, $price) {
        $this->id = $id;
        $this->img = $img;
        $this->date = $date;      
        $this->pavage = $pavage;
        $this->price = $price;
    }

    public function get_id() {
        return $this->id;
    }

    public function get_img() {
        return $this->img;
    }

    public function get_date() {
        return $this->date;
    }

    public function get_pavage() {
        return $this->pavage;
    }

    public function get_price() {
        return $this->price;
    }

}
?>