package fr.uge.univ_eiffel.mediators;

import fr.uge.univ_eiffel.mediators.FactoryClient;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.io.TempDir;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.HashMap;

import static org.junit.jupiter.api.Assertions.*;

class OrderManagerTest {

    private OrderManager orderManager;

    // JUnit 5 TempDir for the file parsing test
    @TempDir
    Path tempDir;

    @BeforeEach
    void setUp() {
        // 1. Initialize Real Components
        // NOTE: This requires 'config.properties' to be in your test resources or root folder!
        try {
            FactoryClient client = FactoryClient.makeFromProps("config.properties");
            InventoryManager inventory = InventoryManager.makeFromProps("config.properties");
            orderManager = new OrderManager(client, inventory);
        } catch (Exception e) {
            // If config is missing, tests will fail here.
            fail("Could not initialize OrderManager. Check config.properties: " + e.getMessage());
        }
    }

    // --- UNIT TEST (Local Logic) ---

    @Test
    void parseInvoice_ShouldReadValidFile() throws IOException {
        // Create a temporary invoice file
        Path file = tempDir.resolve("test_invoice.txt");
        Files.writeString(file, "2-4/c91a09,10\n1-1/05131d,5");

        // Run the parser
        HashMap<String, Integer> result = orderManager.parseInvoice(file.toString());

        // Assert
        assertNotNull(result);
        assertEquals(10, result.get("2-4/c91a09"));
        assertEquals(5, result.get("1-1/05131d"));
    }

    // --- INTEGRATION TESTS (Network Logic) ---

    @Test
    void requestQuote_ShouldReturnValidId() throws IOException {
        // 1. Create a simple shopping list (1 brick)
        HashMap<String, Integer> list = new HashMap<>();
        list.put("2-4/c91a09", 1);

        // 2. Call the API
        OrderManager.Quote quote = orderManager.requestQuote(list);

        // 3. Verify we got a response
        assertNotNull(quote);
        assertNotNull(quote.id(), "Quote ID should not be null");
        assertTrue(quote.price() > 0, "Price should be greater than 0");
    }

    @Test
    void fullOrderFlow_ShouldCompleteWithoutErrors() {
        // This is a "System Test" - it runs the whole cycle.
        // We wrap it in assertDoesNotThrow to ensure it runs smoothly.
        assertDoesNotThrow(() -> {
            // 1. Request
            HashMap<String, Integer> list = new HashMap<>();
            list.put("1-1/05131d", 1); // Smallest cheapest order
            OrderManager.Quote quote = orderManager.requestQuote(list);

            // 2. Confirm
            orderManager.confirmOrder(quote.id());

            // 3. Check Status (Just once, we don't want to loop forever in a test)
            OrderManager.Delivery status = orderManager.deliveryStatus(quote.id());

            assertNotNull(status);
            assertNotNull(status.pendingBricks(), "Pending bricks map should exist (even if empty)");
        });
    }
}