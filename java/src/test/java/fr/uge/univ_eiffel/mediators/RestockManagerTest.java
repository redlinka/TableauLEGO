package fr.uge.univ_eiffel.mediators;

import org.junit.jupiter.api.Test;
import java.util.HashMap;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.*;

class RestockManagerTest {

    @Test
    void calculateRestock_ShouldAddBuffer_WhenNeedIsLow() {
        // ARRANGE
        // We pass nulls because calculateRestock DOES NOT use the dependencies (client, inventory, etc.)
        // It only uses the maps passed as arguments.
        RestockManager manager = new RestockManager(null, null, null, null, null);

        // Scenario: We need 50 units. We currently have 20.
        // Your logic:
        // 1. Base needed = 50 - 20 = 30.
        // 2. Since need (50) < 100, you add a buffer of +100.
        // 3. Total expected = 30 + 100 = 130.
        Map<Integer, Integer> need = Map.of(101, 50);
        Map<Integer, Integer> stock = Map.of(101, 20);

        // ACT
        Map<Integer, Integer> result = manager.calculateRestock(need, stock);

        // ASSERT
        assertTrue(result.containsKey(101));
        assertEquals(130, result.get(101));
    }

    @Test
    void calculateRestock_ShouldNotAddBuffer_WhenNeedIsHigh() {
        // ARRANGE
        RestockManager manager = new RestockManager(null, null, null, null, null);

        // Scenario: We need 150 units. We have 20.
        // Logic: Base needed = 130.
        // Since need (150) >= 100, NO buffer is added.
        // Total expected = 130.
        Map<Integer, Integer> need = Map.of(101, 150);
        Map<Integer, Integer> stock = Map.of(101, 20);

        // ACT
        Map<Integer, Integer> result = manager.calculateRestock(need, stock);

        // ASSERT
        assertEquals(130, result.get(101));
    }

    @Test
    void calculateRestock_ShouldRefill_WhenStockIsLow_EvenIfNoNeed() {
        // ARRANGE
        RestockManager manager = new RestockManager(null, null, null, null, null);

        // Scenario: Nobody wants this brick (Need = 0/Empty), but we have it in stock.
        // If stock is very low (e.g., 10), your logic implies we should keep a buffer.
        Map<Integer, Integer> need = new HashMap<>();
        Map<Integer, Integer> stock = Map.of(101, 10);

        // Logic:
        // Need = 0 (default).
        // Diff = 0 - 10 = -10.
        // Since Need (0) < 100 -> Diff += 100 -> New Diff = 90.
        // Result: Order 90 to top up the buffer.

        // ACT
        Map<Integer, Integer> result = manager.calculateRestock(need, stock);

        // ASSERT
        assertEquals(90, result.get(101), "Should auto-replenish buffer stock even if current demand is zero");
    }

    @Test
    void calculateRestock_ShouldIgnoreItems_NotInStockMap() {
        // ARRANGE
        RestockManager manager = new RestockManager(null, null, null, null, null);

        // Scenario: We need Item 1 and Item 2.
        // But our Stock map only knows about Item 1.
        // Your loop iterates over 'stock.entrySet()', so Item 2 will be ignored.
        Map<Integer, Integer> need = Map.of(1, 50, 2, 50);
        Map<Integer, Integer> stock = Map.of(1, 20);

        // ACT
        Map<Integer, Integer> result = manager.calculateRestock(need, stock);

        // ASSERT
        assertTrue(result.containsKey(1));
        assertFalse(result.containsKey(2), "Items not present in the stock map should be ignored (based on current implementation)");
    }
}