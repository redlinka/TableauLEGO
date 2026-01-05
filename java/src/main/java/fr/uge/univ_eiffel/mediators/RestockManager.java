package fr.uge.univ_eiffel.mediators;

import fr.uge.univ_eiffel.Brick;
import fr.uge.univ_eiffel.security.OfflineVerifier;
import fr.uge.univ_eiffel.security.OnlineVerifier;
import org.jetbrains.annotations.NotNull;

import java.sql.SQLException;
import java.util.*;

public class RestockManager {

    private static List<Integer> orders;
    private static List<List<Piece>> pieces;
    private static int RANGE = 7;

    public static record Piece(int idInventory, int pavageId, int idCatalog){
        @Override
        public String toString(){
            return idInventory + "\t" + pavageId + "\t" + idCatalog;
        }
    }

    /**
     * Calculates the daily average sales per product over the last 7 days.
     *
     * This method processes all the orders to determine how often each product has been sold,
     * and then calculates the average sales per day for each product.
     *
     * @return a Map where the key is the product catalog ID and the value is the daily average sales
     */
    static @NotNull Map<Integer, Integer> calculateDailyAverage() {
        System.out.println("Calculating daily average...");
        // records the amount of each type of coin used
        Map<Integer, Integer> quantity = new HashMap<>();
        for(List<Piece> orderPieces : pieces){
            for(Piece piece : orderPieces) {
                quantity.put(piece.idCatalog, quantity.getOrDefault(piece.idCatalog, 0) + 1);
            }
        }


        Map<Integer, Integer> dailyAverage = new HashMap<>();

        for(Map.Entry<Integer, Integer> a : quantity.entrySet()){
            dailyAverage.put(a.getKey(), (int) Math.ceil((double)a.getValue() / RANGE));
        }

//        System.out.println("-----Daily average is :------");
//        for(Map.Entry<Integer, Integer> entry : dailyAverage.entrySet()){
//            System.out.println(entry.getKey() + "\t" + entry.getValue());
//        }

        return dailyAverage;
    }

    /**
     * Calculates the stock that needs to be prepared based on daily sales averages
     * and the current stock available.
     *
     * This method compares the daily average sales for each product to the current stock.
     * If the stock is below the daily average, it calculates how much more stock is required.
     * It also considers a minimum stock threshold of 10 units for any product.
     *
     * @param dailyAverages a Map where the key is the product catalog ID and the value is the daily average sales
     * @param stock a Map representing the current stock levels, where the key is the product catalog ID and the value is the stock count
     * @return a Map where the key is the product catalog ID and the value is the amount of stock to prepare
     */
    static @NotNull Map<Integer, Integer> calculateStockToPrepare(Map<Integer, Integer> dailyAverages, Map<Integer, Integer> stock){
        System.out.println("Calculating stock to prepare...");
        Map<Integer, Integer> stockToPrepare = new HashMap<>();

        // for each daily average amount of piece
        for(Map.Entry<Integer, Integer> a : dailyAverages.entrySet()){

            //if there is no piece in stock, we set 0 by default
            int pieceAmount = stock.get(a.getKey()) != null ?  stock.get(a.getKey()) : 0 ;
            // average - piece in stock
            int amountToRestock = a.getValue() -  pieceAmount;
            if(pieceAmount < 10){
                amountToRestock +=10;
            }
            if(amountToRestock > 0){
                stockToPrepare.put(a.getKey(), amountToRestock);
            }
        }

        System.out.println("-----Stock to prepare :------");
        for(Map.Entry<Integer, Integer> entry : stockToPrepare.entrySet()){
            System.out.println(entry.getKey() + "\t" + entry.getValue());
        }
        return stockToPrepare;
    }

    /**
     * Parses the stock refill data into a catalog-based invoice.
     *
     * This method converts the stock that needs to be refilled into a human-readable format
     * with product names and quantities to be ordered.
     *
     * @param stockToRefill a Map where the key is the product catalog ID and the value is the amount of stock to refill
     * @param im an InventoryManager used to retrieve the product names from the catalog IDs
     * @return a Map where the key is the product name and the value is the quantity to be ordered
     */
    static @NotNull Map<String, Integer> parseInvoice(@NotNull Map<Integer, Integer> stockToRefill, InventoryManager im){
        Map<String, Integer> invoice  = new HashMap<>();
        for(int catalogId: stockToRefill.keySet()){
            String brickName = im.getBrickTypeName(catalogId);
            invoice.put(brickName, stockToRefill.get(catalogId));
        }

//        System.out.println("-----Catalog ref :------");
//        for(Map.Entry<String, Integer> entry : invoice.entrySet()){
//            System.out.println(entry.getKey() + "\t" + entry.getValue());
//        }
        return invoice;
    }

    /**
     * Refills the inventory by ordering the necessary stock based on the provided invoice.
     *
     * This method requests a quote from the supplier, confirms the order, and waits for
     * the delivery status. Once the order is complete, it adds the delivered items to
     * the inventory, verifying each brick’s certificate before adding.
     *
     * @param inventory the InventoryManager used to add bricks to the inventory
     * @param orderer the OrderManager responsible for handling the order process
     * @param client the FactoryClient used for client-side operations such as quote requests and verification
     * @param invoice a Map containing the product names and quantities to be ordered
     * @throws Exception if any errors occur during the order process or inventory update
     */
    static void refillInventory(InventoryManager inventory, @NotNull OrderManager orderer, @NotNull FactoryClient client, Map<String, Integer> invoice) throws Exception {
        var quote = orderer.requestQuote((HashMap<String, Integer>) invoice);
        System.out.println("currently asking confirmation of quote: " + quote);

        orderer.confirmOrder(quote.id());

        OrderManager.Delivery status;

        do {
            //we check every 500 millisecs
            Thread.sleep(500);
            status = orderer.deliveryStatus(quote.id());
            System.out.println("pending bricks :" + status.pendingBricks());
        } while (!status.completed());

        System.out.println("Order completed. Adding bricks...");
        var publicKey = client.signaturePublicKey();
        OfflineVerifier offline = new OfflineVerifier(publicKey);
        OnlineVerifier online = new OnlineVerifier(client);

        for (Brick brick : status.bricks()) {
            //Online verification
            boolean valid = online.verify(brick);
            // Offline verification
            boolean offlineVerif = offline.verify(brick);
            boolean added = inventory.add(brick);

            if (valid && offlineVerif && added) {
                System.out.println("Brick " + brick.name() + " added to inventory");
            } else {
                System.out.println("Brick " + brick.name() + " failed verification or already exists");
            }
        }

    }

    /**
     * Performs the daily restocking process by analyzing orders, calculating daily averages,
     * determining the stock needed to prepare, and placing orders to refill the inventory.
     *
     * This method is the main entry point for the daily restock procedure. It retrieves orders,
     * calculates the necessary stock adjustments, and places an order to ensure the inventory is sufficiently stocked.
     *
     * @param im the InventoryManager used to retrieve orders and get data
     * @return true if the restocking process completed successfully, false otherwise
     * @throws SQLException if a database error occurs while retrieving order or stock data
     */
    public static boolean dailyRestockage(InventoryManager im) throws SQLException {

        pieces = new ArrayList<>();

        try {
            //retrieve orders from the last RANGE days
            orders = im.getOrders(RANGE);
            System.out.println("Getting orders pieces");
            //retrieve the items used in each order
            for(int x : orders){
                List<Piece> c = im.getOrderPieces(x);
                pieces.add(c);
            }
            // calculates the average of the different types of coins used per day
            Map<Integer, Integer> cda = calculateDailyAverage();
            // calculates the number of parts to be ordered for each type
            Map<Integer, Integer> cstp = calculateStockToPrepare(cda,  im.getStock());

            //order the pieces
            var invoice = parseInvoice(cstp, im);
            if(invoice.isEmpty()){
                System.out.println("No bricks found");
                return true;
            }
            var client = FactoryClient.makeFromProps("config.properties");
            refillInventory(im, new OrderManager(client, im), client, invoice);
            im.addRestockHistory(invoice);
            return true;
        } catch (SQLException e) {
            throw new RuntimeException(e);
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    }

//    public static void main(String[] args) throws SQLException {
//
//        if(!dailyRestockage(InventoryManager.makeFromProps("config.properties"))){
//            System.err.println("Error during daily restocking");
//        }
//    }
}
