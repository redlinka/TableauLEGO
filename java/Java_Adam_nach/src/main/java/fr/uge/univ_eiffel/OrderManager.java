package fr.uge.univ_eiffel;

import com.google.gson.Gson;
import com.google.gson.JsonObject;

import java.io.BufferedReader;
import java.io.FileReader;
import java.io.IOException;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;

public class OrderManager {
    private final FactoryClient client;
    private final InventoryManager inventory;
    private final Gson gson = new Gson();

    record Quote (String id, double price, long delay) {}
    record Delivery (boolean completed, List<Brick> bricks, HashMap<String, Integer> pendingBricks) {}

    public OrderManager(FactoryClient client, InventoryManager inventory) {
        this.client = client;
        this.inventory = inventory;
    }

    public HashMap<String, Integer> parseInvoice(String invoicePath) {

        if (invoicePath == null || invoicePath.trim().isEmpty()) {
            return null;
        }
        HashMap<String, Integer> missing = new HashMap<>();

        try (BufferedReader br = new BufferedReader(new FileReader(invoicePath))) {
            String line;
            while ((line = br.readLine()) != null) {

                if (line == null || line.trim().isEmpty()) {
                    throw new IllegalArgumentException("Empty line detected in invoice");
                }
                String[] parts = line.split(",");

                if (parts.length != 2) {
                    throw new IllegalArgumentException("Invalid format in " + line);
                }
                String blockKey = parts[0].trim().toLowerCase();
                int qte = Integer.parseInt(parts[1].trim());
                missing.put(blockKey, qte);
            }
        } catch (IOException e) {
            System.err.println("Error reading invoice file: " + invoicePath);
            e.printStackTrace();
            return null;
        }
        return missing;
    }

    public Quote requestQuote(HashMap<String, Integer> bricks) throws IOException {

        JsonObject req = new JsonObject();
        for (HashMap.Entry<String, Integer> entry : bricks.entrySet()) {
            req.addProperty(entry.getKey(), entry.getValue());
        }
        JsonObject res = client.requestQuote(req);

        return new Quote(
                res.get("id").getAsString(),
                res.get("price").getAsDouble(),
                res.get("delay").getAsLong()
        );
    }

    public void confirmOrder(String id) throws IOException {
        client.confirmOrder(id);
    }

    public Delivery deliveryStatus(String id) throws IOException {

        JsonObject res = client.deliver(id);
        boolean completed = res.getAsJsonObject("pending_blocks").size() == 0;

        List<Brick> built = new ArrayList<>();
        if (res.has("built_blocks") && !res.get("built_blocks").isJsonNull()) {
            for (var elem : res.getAsJsonArray("built_blocks")) {
                var o = elem.getAsJsonObject();
                built.add(new Brick(
                        o.get("name").getAsString(),
                        o.get("serial").getAsString(),
                        o.get("certificate").getAsString())
                );
            }
        }
        HashMap<String, Integer> pending = new HashMap<>();
        JsonObject pendJson = res.getAsJsonObject("pending_blocks");
        for (String key : pendJson.keySet()) {
            pending.put(key, pendJson.get(key).getAsInt());
        }
        return new Delivery(completed, built, pending);
    }

}
