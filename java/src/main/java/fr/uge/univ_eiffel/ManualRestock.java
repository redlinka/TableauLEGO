package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.mediators.*;
import fr.uge.univ_eiffel.mediators.legofactory.FactoryLoader;
import fr.uge.univ_eiffel.mediators.legofactory.LegoFactory;
import fr.uge.univ_eiffel.mediators.payment_methods.PaymentMethod;
import fr.uge.univ_eiffel.mediators.payment_methods.PoWMethod;
import fr.uge.univ_eiffel.mediators.security.BrickVerifier;
import fr.uge.univ_eiffel.mediators.security.OfflineVerifier;

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
            final LegoFactory factory = FactoryLoader.loadFromProps("config.properties");
            final OrderManager orderer = new OrderManager(factory, inventory);
            final PaymentMethod payer = new PoWMethod(factory);
            String publicKey = factory.signaturePublicKey();
            final BrickVerifier verifier = new OfflineVerifier(publicKey);
            final RestockManager restorer = new RestockManager(inventory, factory, orderer, payer, verifier);

            restorer.restockFromFile(filePath);
        } catch (Exception e) {e.printStackTrace();}
    }
}