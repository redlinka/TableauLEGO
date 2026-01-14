package fr.uge.univ_eiffel.mediators;

import fr.uge.univ_eiffel.mediators.legofactory.LegoFactory;
import fr.uge.univ_eiffel.mediators.payment_methods.PaymentMethod;
import fr.uge.univ_eiffel.mediators.security.BrickVerifier;
import org.jetbrains.annotations.NotNull;

import java.sql.SQLException;
import java.util.*;
import java.util.stream.Collectors;

import static java.lang.Thread.sleep;

public class RestockManager {

    private static List<Integer> orders;
    private static List<List<Piece>> pieces;
    private static int RANGE = 7;

    private final InventoryManager inventory;
    private final LegoFactory factory;
    private final OrderManager orderer;
    private final PaymentMethod paymentMethod;
    private final BrickVerifier verifier;

    static int MIN_STOCK = 10;

    public RestockManager(InventoryManager inventory, LegoFactory factory, OrderManager orderer, PaymentMethod paymentMethod, BrickVerifier verifier) {
        this.inventory = inventory;
        this.factory = factory;
        this.orderer = orderer;
        this.paymentMethod = paymentMethod;
        this.verifier = verifier;
    }

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
        // records the amount of each type of brick used
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
     * @param need a Map where the key is the product catalog ID and the value is the daily average sales
     * @param stock a Map representing the current stock levels, where the key is the product catalog ID and the value is the stock count
     * @return a Map where the key is the product catalog ID and the value is the amount of stock to prepare
     */
    public @NotNull Map<Integer, Integer> calculateRestock(@NotNull Map<Integer, Integer> need, Map<Integer, Integer> stock){

        System.out.println("Calculating stock to prepare..."+ need.size() + " needed items and " + stock.size() + " stock items.");
        Map<Integer, Integer> stockToPrepare = new HashMap<>();

        // for each daily average amount of piece
        for(Map.Entry<Integer, Integer> a : stock.entrySet()){

            //if there is no piece in stock, we set 0 by default
            int pieceAmount = need.get(a.getKey()) != null ?  need.get(a.getKey()) : 0 ;
            // average - piece in stock
            int amountToRestock = pieceAmount - a.getValue();
            if(pieceAmount < 100){
                amountToRestock +=100;
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
     * @return a Map where the key is the product name and the value is the quantity to be ordered
     */
    public @NotNull Map<String, Integer> parseQuoteRequest(@NotNull Map<Integer, Integer> stockToRefill){
        Map<String, Integer> invoice  = new HashMap<>();
        for(int catalogId: stockToRefill.keySet()){
            String brickName = inventory.getBrickTypeName(catalogId);
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
     * @param quoteRequest a Map containing the product names and quantities to be ordered
     * @throws Exception if any errors occur during the order process or inventory update
     */
    public void refillInventory(Map<String, Integer> quoteRequest, Integer tilingID) throws Exception {

        var quote = orderer.requestQuote((HashMap<String, Integer>) quoteRequest);

        System.out.println("currently asking confirmation of quote: " + quote);
        System.out.println("balance: " + factory.balance());
        if (quote.price() > factory.balance()) {
            System.err.println("Insufficient balance to place the order. Required: " + quote.price() + ", Available: " + factory.balance());
            System.out.println("Trying to pay...");
            paymentMethod.pay(quote.price());
            System.out.println("New balance: " + factory.balance());
        }

        orderer.confirmOrder(quote.id());

        OrderManager.Delivery status;

        var publicKey = factory.signaturePublicKey();

        do {
            sleep(5000); // wait 5 seconds before polling again
            status = orderer.deliveryStatus(quote.id());
            System.out.println("pending bricks :" + status.pendingBricks());
            System.out.println("Adding already ordered bricks...");

            /*
            for (Brick brick : status.bricks()) {
                boolean valid = verifier.verify(brick);
            }
            */
            inventory.addBatch(status.bricks(), tilingID);

            status.bricks().clear();

        } while (!status.completed());

        System.out.println("Order completed. Adding bricks...");
    }

    /**
     * Directly restocks inventory from a provided file without checking current stock levels.
     * Useful for manual bulk orders or initial seeding.
     *
     * @param filePath Path to the file containing brick counts (format compatible with OrderManager).
     * @return true if successful.
     * @throws Exception if parsing or ordering fails.
     */
    public boolean restockFromFile(String filePath) throws Exception {
        System.out.println("Processing direct restock from file: " + filePath);
        // 1. Parse the file to get the order
        Map<String, Integer> order = OrderManager.parseSolutionCounts(filePath);

        if (order.isEmpty()) {
            System.out.println("No bricks found in file.");
            return false;
        }

        System.out.println("Ordering bricks directly: " + order);

        // 2. Place the order. Pass NULL for tilingID since this is a general restock.
        refillInventory(order, null);

        // 3. Log it in history
        inventory.addRestockHistory(order);

        return true;
    }

    /**
     * Performs reactive restocking based on the inputed tiling solution.
     * This method analyzes the provided tiling solution to determine
     * which pieces need to be restocked in the inventory.
     * @param solutionPath the path to the tiling solution file
     * @return true if the restocking process completed successfully, false otherwise
     * @throws Exception if any errors occur during the restocking process
     */
    public boolean reactiveRestockage(String solutionPath, Integer tilingID) throws Exception {
        Map<Integer, Integer> stock = inventory.getFullStock();
        Map<Integer, Integer> needed = OrderManager.parseSolutionCounts(solutionPath).entrySet().stream().collect(Collectors.toMap(
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

        // calculates the number of parts to be ordered for each type
        Map<Integer, Integer> difference = calculateRestock(needed, stock);

        // if the difference is positive we need to order more pieces
        inventory.reserveStockForTiling(needed, tilingID);

        //order the pieces
        Map<String, Integer> order = parseQuoteRequest(difference);

        if(order.isEmpty()){
            System.out.println("No bricks found");
            return true;
        }

        System.out.println("Order: " + order);
        refillInventory(order, tilingID);
        inventory.addRestockHistory(order);
        return true;
    }

    /**
     * Performs the daily restocking process by analyzing orders, calculating daily averages,
     * determining the stock needed to prepare, and placing orders to refill the inventory.
     *
     * This method is the main entry point for the daily restock procedure. It retrieves orders,
     * calculates the necessary stock adjustments, and places an order to ensure the inventory is sufficiently stocked.
     *
     * @return true if the restocking process completed successfully, false otherwise
     * @throws SQLException if a database error occurs while retrieving order or stock data
     */
    public boolean dailyRestockage() throws SQLException {

        pieces = new ArrayList<>();

        try {
            //retrieve orders from the last RANGE days
            orders = inventory.getOrders(RANGE);
            System.out.println("Getting orders pieces");
            //retrieve the items used in each order
            for(int x : orders){
                List<Piece> c = inventory.getOrderPieces(x);
                pieces.add(c);
            }
            // calculates the average of the different types of coins used per day
            Map<Integer, Integer> dailyAv = calculateDailyAverage();
            // calculates the number of parts to be ordered for each type
            Map<Integer, Integer> difference = calculateRestock(dailyAv,  inventory.getFullStock());

            //order the pieces
            Map<String, Integer> invoice = parseQuoteRequest(difference);
            if(invoice.isEmpty()){
                System.out.println("No bricks found");
                return true;
            }
            refillInventory(invoice, null);
            inventory.addRestockHistory(invoice);
            return true;
        } catch (SQLException e) {
            throw new RuntimeException(e);
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    }
}
