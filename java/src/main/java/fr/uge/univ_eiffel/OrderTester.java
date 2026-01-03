package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.mediators.FactoryClient;
import fr.uge.univ_eiffel.mediators.InventoryManager;
import fr.uge.univ_eiffel.mediators.OrderManager;
import fr.uge.univ_eiffel.security.OfflineVerifier;

import java.util.HashMap;

public class OrderTester {
    public static void main(String[] args) {
        System.out.println("🔧 Initializing Manual Order Test...");

        // 1. Setup the managers
        try (InventoryManager inventory = InventoryManager.makeFromProps("config.properties")) {
            FactoryClient client = FactoryClient.makeFromProps("config.properties");
            OrderManager orderer = new OrderManager(client, inventory);

            // 2. Create your Shopping List
            // KEYS MUST BE: "width-height/hexCode"
            HashMap<String, Integer> shoppingList = new HashMap<>();
            shoppingList.put("1-1/05131d", 1); // 1 Black 1x1 bricks

            System.out.println("🛒 Shopping List: " + shoppingList);

            // 3. Request a Price Quote
            System.out.println("... Requesting Quote ...");
            OrderManager.Quote quote = orderer.requestQuote(shoppingList);
            System.out.println("✅ Quote Received! ID: " + quote.id() + " | Price: " + quote.price() + " | Est. Delay: " + quote.delay());

            // 4. Confirm the Order
            System.out.println("... Confirming Order ...");
            orderer.confirmOrder(quote.id());
            System.out.println("✅ Order Confirmed! Factory is printing...");

            // 5. Wait for Delivery (The Polling Loop)
            OrderManager.Delivery status;
            do {
                Thread.sleep(500); // Don't spam the API
                status = orderer.deliveryStatus(quote.id());
                System.out.println("⏳ Status: " + status);
            } while (!status.completed());

            System.out.println("📦 DELIVERY ARRIVED!");

            // 6. Verify and Store the Bricks
            String publicKey = client.signaturePublicKey();
            OfflineVerifier verifier = new OfflineVerifier(publicKey);
            int successCount = 0;

            for (Brick brick : status.bricks()) {
                // Offline Cryptographic Check
                boolean isLegit = verifier.verify(brick);

                if (isLegit) {
                    // Try adding to DB
                    boolean added = inventory.add(brick);
                    if (added) {
                        System.out.println("✨ Saved: " + brick.name() + " [" + brick.serial().substring(0, 6) + "...]");
                        successCount++;
                    }
                } else {
                    System.err.println("❌ FAKE BRICK DETECTED: " + brick.name());
                }
            }

            System.out.println("🎉 Done! Successfully added " + successCount + " bricks to the database.");

        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}