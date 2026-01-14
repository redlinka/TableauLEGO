package fr.uge.univ_eiffel.mediators;

import org.junit.jupiter.api.Test;
import static org.junit.jupiter.api.Assertions.*;

class InventoryManagerTest {

    // --- 1. Pricing Logic ---

    @Test
    void computeUnitPrice_ShouldReturnBasePrice_ForSmallestBrick() {
        // Brick 1x1 -> Area = 1.
        // Formula: 0.01 * 0.9 ^ (log2(1))
        // log2(1) = 0.
        // 0.9^0 = 1.
        // Result = 0.01
        double price = InventoryManager.computeUnitPrice(1, 1);
        assertEquals(0.01, price, 0.000001, "1x1 Brick should cost exactly 0.01");
    }

    @Test
    void computeUnitPrice_ShouldDecrease_ForLargerBricks() {
        // Brick 2x4 -> Area = 8.
        // log2(8) = 3.
        // 0.9^3 = 0.729.
        // Result = 0.00729.
        double price = InventoryManager.computeUnitPrice(2, 4);
        assertEquals(0.00729, price, 0.00001, "2x4 Brick price calculation matches formula");
    }

    // --- 2. Data Conversion (Hex to Bytes) ---

    @Test
    void hexToBytes_ShouldConvertValidString() {
        // "c91a09" -> Red color in Lego
        // Expecting 3 bytes: [0xC9, 0x1A, 0x09]
        // In Java signed bytes: -55, 26, 9
        String hex = "c91a09";
        byte[] result = InventoryManager.hexToBytes(hex);

        assertNotNull(result);
        assertEquals(3, result.length);
        assertEquals((byte) 0xC9, result[0]);
        assertEquals((byte) 0x1A, result[1]);
        assertEquals((byte) 0x09, result[2]);
    }

    @Test
    void hexToBytes_ShouldHandleEmptyString() {
        byte[] result = InventoryManager.hexToBytes("");
        assertEquals(0, result.length);
    }
}