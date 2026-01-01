package fr.uge.univ_eiffel.manager;

import fr.uge.univ_eiffel.Brick;
import fr.uge.univ_eiffel.CertificateVerification;
import fr.uge.univ_eiffel.butlers.FactoryClient;

import java.sql.SQLException;
import java.util.*;

public class RestockManager {

    private static List<Integer> orders;
    private static List<List<Piece>> pieces;

    public static record Piece(int idInventory, int pavageId, int idCatalog){
        @Override
        public String toString(){
            return idInventory + "\t" + pavageId + "\t" + idCatalog;
        }
    }

    //calculate daily average sales per product.
    static Map<Integer, Integer> calculateDailyAverage() {
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
            dailyAverage.put(a.getKey(), (int) Math.ceil((double)a.getValue() / 7));
        }

//        System.out.println("-----Daily average is :------");
//        for(Map.Entry<Integer, Integer> entry : dailyAverage.entrySet()){
//            System.out.println(entry.getKey() + "\t" + entry.getValue());
//        }

        return dailyAverage;
    }

    static Map<Integer, Integer> calculateStockToPrepare(Map<Integer, Integer> dailyAverages, Map<Integer, Integer> stock){
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

    static Map<String, Integer> parseInvoice(Map<Integer, Integer> stockToRefill, InventoryManager im){
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

    static void refillInventory(InventoryManager inventory, OrderManager orderer, FactoryClient client, Map<String, Integer> invoice) throws Exception {
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

        for (Brick brick : status.bricks()) {
            //Online verification
            boolean valid = client.verify(brick.name(), brick.serial(), brick.certificate());
            // Offline verification
            boolean offlineVerif = CertificateVerification.verify(brick, publicKey);
            boolean added = inventory.add(brick);

            if (valid && offlineVerif && added) {
                System.out.println("Brick " + brick.name() + " added to inventory");
            } else {
                System.out.println("Brick " + brick.name() + " failed verification or already exists");
            }
        }

    }

    static boolean dailyRestockage(InventoryManager im) throws SQLException {

        pieces = new ArrayList<>();
        try {
            //retrieve orders from the last 7 days
            orders = im.getOrders(7);
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
            return true;
        } catch (SQLException e) {
            throw new RuntimeException(e);
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    }

    public static void main(String[] args) throws SQLException {

        if(!dailyRestockage(InventoryManager.makeFromProps("config.properties"))){
            System.err.println("Error during daily restocking");
        }
    }
}
