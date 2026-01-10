package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.mediators.FactoryClient;
import fr.uge.univ_eiffel.mediators.InventoryManager;
import fr.uge.univ_eiffel.mediators.OrderManager;
import fr.uge.univ_eiffel.mediators.RestockManager;
import fr.uge.univ_eiffel.payment_methods.PaymentMethod;
import fr.uge.univ_eiffel.payment_methods.PoWMethod;
import fr.uge.univ_eiffel.security.BrickVerifier;
import fr.uge.univ_eiffel.security.OfflineVerifier;

/**
 * CLI utility to manually trigger a restock from a specific file.
 * Bypasses the daily average logic and reservations, directly ordering
 * the quantities specified in the input file.
 * * Usage: java ManualRestocker <path/to/order.txt>
 */
public class ManualRestock {
    public static void main(String[] args) {

        // Check if the user provided the argument
        if (args.length < 1) {
            System.err.println("Usage: java ManualRestocker <path/to/order_file.txt>");
            return;
        }

        // Grab the file path from the first argument
        String filePath = args[0];

        try {
            final InventoryManager inventory = InventoryManager.makeFromProps("config.properties");
            final FactoryClient client = FactoryClient.makeFromProps("config.properties");
            final OrderManager orderer = new OrderManager(client, inventory);
            final PaymentMethod payer = new PoWMethod(client);
            String publicKey = client.signaturePublicKey();
            final BrickVerifier verifier = new OfflineVerifier(publicKey);
            final RestockManager restorer = new RestockManager(inventory, client, orderer, payer, verifier);

            restorer.restockFromFile(filePath);

        } catch (Exception e) {e.printStackTrace();}
    }
}