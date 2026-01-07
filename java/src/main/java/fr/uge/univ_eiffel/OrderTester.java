package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.mediators.FactoryClient;
import fr.uge.univ_eiffel.mediators.InventoryManager;
import fr.uge.univ_eiffel.mediators.OrderManager;
import fr.uge.univ_eiffel.mediators.RestockManager;
import fr.uge.univ_eiffel.payment_methods.PaymentMethod;
import fr.uge.univ_eiffel.payment_methods.pow.PoWMethod;
import fr.uge.univ_eiffel.security.BrickVerifier;
import fr.uge.univ_eiffel.security.OfflineVerifier;

import java.sql.SQLException;
import java.util.HashMap;
import java.util.Map;
import java.util.stream.Collectors;

public class OrderTester {
    public static void main(String[] args) {
        try {
            FactoryClient client = FactoryClient.makeFromProps("config.properties");
            InventoryManager inventory = InventoryManager.makeFromProps("config.properties");
            OrderManager orderer = new OrderManager(client, inventory);
            PaymentMethod paymentMethod = new PoWMethod(client);
            BrickVerifier verifier = new OfflineVerifier(client.signaturePublicKey());
            RestockManager restocker = new RestockManager(inventory, orderer, client, paymentMethod, verifier);

            String solutionFilePath = "output.txt";

            Map<Integer, Integer> stock = inventory.getStock();
            System.out.println("Current stock: " + stock);
            Map<Integer, Integer> needed = OrderManager.parseSolutionCounts(solutionFilePath).entrySet().stream().collect(Collectors.toMap(
                    e -> {
                        try {
                            return inventory.getCatalogId(e.getKey());
                        } catch (SQLException ex) {
                            throw new RuntimeException(ex);
                        }
                    },
                    Map.Entry::getValue,
                    Integer::sum
            ));
            //order the pieces
            Map<String, Integer> order = parseInvoice(needed, inventory);
            if(order.isEmpty()){
                System.out.println("No bricks found");
                return;
            }
            System.out.println("Order: " + order);
            refillInventory(inventory, orderer, client, order);
            inventory.addRestockHistory(order);

        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}