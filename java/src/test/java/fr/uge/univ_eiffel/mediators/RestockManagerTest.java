package fr.uge.univ_eiffel.mediators;

import org.junit.jupiter.api.Test;
import java.util.HashMap;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.*;

class RestockManagerTest {

    /**
     * Test Logic:
     * We need 50/day. We have 20.
     * Math: 50 - 20 = 30 needed.
     * (Above minimum buffer of 10, so simple subtraction).
     */
    @Test
    void calculateStockToPrepare_StandardRefill() {
        // ARRANGE
        Map<Integer, Integer> dailyAvg = new HashMap<>();
        dailyAvg.put(101, 50); // Catalog ID 101, needs 50/day

        Map<Integer, Integer> currentStock = new HashMap<>();
        currentStock.put(101, 20); // Catalog ID 101, has 20

        // ACT
        Map<Integer, Integer> result = RestockManager.calculateStockToPrepare(dailyAvg, currentStock);

        // ASSERT
        assertTrue(result.containsKey(101), "Should return an order for item 101");
        assertEquals(30, result.get(101), "Should order exactly the difference (50 - 20 = 30)");
    }

    /**
     * Test Logic:
     * We need 50/day. We have 2 (CRITICAL LOW).
     * Math: (50 - 2) = 48 needed.
     * Buffer Rule: If stock < 10, add 10 extra?
     * Your Code: `if(pieceAmount < 10) { amountToRestock += 10; }`
     * Result: 48 + 10 = 58.
     */
    @Test
    void calculateStockToPrepare_CriticalLow_AddsBuffer() {
        // ARRANGE
        Map<Integer, Integer> dailyAvg = Map.of(101, 50);
        Map<Integer, Integer> currentStock = Map.of(101, 2); // Only 2 left!

        // ACT
        Map<Integer, Integer> result = RestockManager.calculateStockToPrepare(dailyAvg, currentStock);

        // ASSERT
        // Expected: (50 - 2) + 10 buffer = 58
        assertEquals(58, result.get(101), "Should add buffer of 10 when stock is critically low (<10)");
    }

    /**
     * Test Logic:
     * We need 10/day. We have 100.
     * Math: 10 - 100 = -90.
     * Result: Should NOT order anything (no negative numbers in invoice).
     */
    @Test
    void calculateStockToPrepare_SufficientStock_NoOrder() {
        // ARRANGE
        Map<Integer, Integer> dailyAvg = Map.of(101, 10);
        Map<Integer, Integer> currentStock = Map.of(101, 100);

        // ACT
        Map<Integer, Integer> result = RestockManager.calculateStockToPrepare(dailyAvg, currentStock);

        // ASSERT
        assertFalse(result.containsKey(101), "Should not create an order entry if stock is sufficient");
    }

    /**
     * Test Logic:
     * Item is completely missing from stock map (null).
     * Your code handles this: `stock.get(...) != null ? ... : 0`.
     */
    @Test
    void calculateStockToPrepare_ItemMissingFromStock_AssumesZero() {
        // ARRANGE
        Map<Integer, Integer> dailyAvg = Map.of(999, 5);
        Map<Integer, Integer> currentStock = new HashMap<>(); // Empty stock

        // ACT
        Map<Integer, Integer> result = RestockManager.calculateStockToPrepare(dailyAvg, currentStock);

        // ASSERT
        // Logic: (5 - 0) + 10 (buffer because 0 < 10) = 15
        assertEquals(15, result.get(999));
    }
}