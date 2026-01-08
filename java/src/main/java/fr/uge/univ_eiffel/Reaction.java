package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.mediators.FactoryClient;
import fr.uge.univ_eiffel.mediators.InventoryManager;
import fr.uge.univ_eiffel.mediators.OrderManager;
import fr.uge.univ_eiffel.mediators.RestockManager;
import fr.uge.univ_eiffel.payment_methods.PaymentMethod;
import fr.uge.univ_eiffel.payment_methods.PoWMethod;
import fr.uge.univ_eiffel.security.BrickVerifier;
import fr.uge.univ_eiffel.security.OfflineVerifier;

public class Reaction {
    public static void main(String[] args) {

        try {
            final InventoryManager inventory = InventoryManager.makeFromProps("config.properties");
            final FactoryClient client = FactoryClient.makeFromProps("config.properties");
            final OrderManager orderer = new OrderManager(client, inventory);
            final PaymentMethod payer = new PoWMethod(client);
            String publicKey = client.signaturePublicKey();
            final BrickVerifier verifier = new OfflineVerifier(publicKey);
            final RestockManager restorer = new RestockManager(inventory, client, orderer, payer, verifier);

            String solutionPath = "output.txt";
            int imageId = 3;
            int lastId = inventory.newConfirmedTiling(solutionPath, imageId);

            restorer.reactiveRestockage(solutionPath, lastId);

        } catch (Exception e) {e.printStackTrace();}
    }
}