package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.mediators.*;
import fr.uge.univ_eiffel.mediators.legofactory.FactoryLoader;
import fr.uge.univ_eiffel.mediators.legofactory.LegoFactory;
import fr.uge.univ_eiffel.mediators.payment_methods.PaymentMethod;
import fr.uge.univ_eiffel.mediators.payment_methods.PoWMethod;
import fr.uge.univ_eiffel.mediators.security.BrickVerifier;
import fr.uge.univ_eiffel.mediators.security.OfflineVerifier;

public class ProactionRestock {
    public static void main(String[] args) {

        try {
            final InventoryManager inventory = InventoryManager.makeFromProps("config.properties");
            final LegoFactory factory = FactoryLoader.loadFromProps("config.properties");
            final OrderManager orderer = new OrderManager(factory, inventory);
            final PaymentMethod payer = new PoWMethod(factory);
            String publicKey = factory.signaturePublicKey();
            final BrickVerifier verifier = new OfflineVerifier(publicKey);
            final RestockManager restorer = new RestockManager(inventory, factory, orderer, payer, verifier);

            restorer.dailyRestockage();

        } catch (Exception e) {e.printStackTrace();}
    }
}
