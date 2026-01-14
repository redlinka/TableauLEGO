package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.mediators.*;
import fr.uge.univ_eiffel.mediators.legofactory.FactoryLoader;
import fr.uge.univ_eiffel.mediators.legofactory.LegoFactory;
import fr.uge.univ_eiffel.mediators.payment_methods.PaymentMethod;
import fr.uge.univ_eiffel.mediators.payment_methods.PoWMethod;
import fr.uge.univ_eiffel.mediators.security.BrickVerifier;
import fr.uge.univ_eiffel.mediators.security.OfflineVerifier;

/**
 * CLI utility to create a tiling entry and trigger a reactive restock.
 * 1. Uses the provided path directly as the name in the database.
 * 2. Creates a TILLING entry.
 * 3. Uses the file at that path to calculate stock differences and order.
 * Usage: java ReactiveRestocker <hashed_file.txt> <image_id>
 */
public class ReactionRestock {
    public static void main(String[] args) {

        if (args.length < 2) {
            System.err.println("Usage: java ReactiveRestocker <hashed_file> <image_id>");
            return;
        }

        String solutionPath = args[0];
        int imageId;

        try {
            imageId = Integer.parseInt(args[1]);
        } catch (NumberFormatException e) {
            System.err.println("Error: image_id must be an integer.");
            return;
        }

        try {
            // Setup dependencies
            final InventoryManager inventory = InventoryManager.makeFromProps("config.properties");
            final LegoFactory factory = FactoryLoader.loadFromProps("config.properties");
            final OrderManager orderer = new OrderManager(factory, inventory);
            final PaymentMethod payer = new PoWMethod(factory);
            String publicKey = factory.signaturePublicKey();
            final BrickVerifier verifier = new OfflineVerifier(publicKey);
            final RestockManager restorer = new RestockManager(inventory, factory, orderer, payer, verifier);

            // 1. Use the raw path string as the DB entry
            System.out.println("Processing file: " + solutionPath);

            // 2. Create the new tiling entry with the path as the name
            System.out.println("Creating new tiling entry for Image ID " + imageId + "...");
            int tilingId = inventory.newConfirmedTiling(solutionPath, imageId);
            System.out.println("✅ Tiling created with ID: " + tilingId);

            // 3. Run the reactive restock using the full path
            System.out.println("Starting reactive restock for Tiling " + tilingId + "...");
            restorer.reactiveRestockage(solutionPath, tilingId);

        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}