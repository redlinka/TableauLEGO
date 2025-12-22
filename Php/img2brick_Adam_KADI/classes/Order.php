<?php
class Order {
    public string $id;
    public string $variant;
    public string $boardSize;
    public string $imagePath;
    public function __construct(string $variant, string $boardSize, string $imagePath) {
        $this->id = 'CMD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $this->variant = $variant;
        $this->boardSize = $boardSize;
        $this->imagePath = $imagePath;
    }
}
