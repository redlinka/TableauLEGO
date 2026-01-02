package fr.uge.univ_eiffel.mediators;

import org.junit.jupiter.api.Test;
import static org.junit.jupiter.api.Assertions.*;

class InventoryManagerTest {

    // --- PRICING FORMULA TESTS ---

    @Test
    void computeUnitPrice_StandardBrick() {
        // Test a 2x4 brick (Area = 8)
        // Formula: 0.01 * 0.9 ^ (log2(8))
        // log2(8) = 3
        // 0.01 * 0.9^3 = 0.01 * 0.729 = 0.00729

        double price = InventoryManager.computeUnitPrice(2, 4);

        assertEquals(0.00729, price, 0.00001, "Price calculation for 2x4 should be accurate");
    }

    @Test
    void computeUnitPrice_SmallestBrick() {
        // Test a 1x1 brick (Area = 1)
        // log2(1) = 0
        // 0.01 * 0.9^0 = 0.01 * 1 = 0.01

        double price = InventoryManager.computeUnitPrice(1, 1);

        assertEquals(0.01, price, 0.00001, "1x1 brick should be base price (0.01)");
    }

    // --- HEX CONVERSION TESTS ---

    @Test
    void hexToBytes_ValidRedColor() {
        // "c91a09" is LEGO Red
        // Expect bytes: [0xC9, 0x1A, 0x09]
        // In Java signed bytes: -55, 26, 9

        byte[] result = InventoryManager.hexToBytes("c91a09");

        assertNotNull(result);
        assertEquals(3, result.length);
        assertEquals((byte) 0xC9, result[0]);
        assertEquals((byte) 0x1A, result[1]);
        assertEquals((byte) 0x09, result[2]);
    }

    @Test
    void hexToBytes_ZeroString() {
        // "0000" -> [0, 0]
        byte[] result = InventoryManager.hexToBytes("0000");

        assertEquals(2, result.length);
        assertEquals(0, result[0]);
        assertEquals(0, result[1]);
    }

    @Test
    void hexToBytes_EmptyString() {
        byte[] result = InventoryManager.hexToBytes("");
        assertEquals(0, result.length);
    }
}