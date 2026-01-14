package fr.uge.univ_eiffel.mediators;

import com.google.gson.Gson;
import com.google.gson.JsonObject;
import fr.uge.univ_eiffel.mediators.legofactory.LegoFactory;

import java.io.BufferedReader;
import java.io.FileReader;
import java.io.IOException;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;

public class OrderManager {
    private final LegoFactory factory;
    private final InventoryManager inventory;
    private final Gson gson = new Gson();

    public record Quote (String id, double price, long delay) {}
    public record Delivery (boolean completed, List<Brick> bricks, HashMap<String, Integer> pendingBricks) {}

    public OrderManager(LegoFactory factory, InventoryManager inventory) {
        this.factory = factory;
        this.inventory = inventory;
    }

    /**
     * Parses a solution output file and counts the occurrences of each brick.
     * @param solutionPath The path to the solution file (e.g., "output.txt").
     * @return A Map where Key = Brick Name (e.g., "6-24/1591cb") and Value = Quantity.
     */
    public static HashMap<String, Integer> parseSolutionCounts(String solutionPath) {
        HashMap<String, Integer> brickCounts = new HashMap<>();

        try (BufferedReader reader = new BufferedReader(new FileReader(solutionPath))) {
            // 1. Skip the header line (Price and Quality scores)
            reader.readLine();

            String line;
            while ((line = reader.readLine()) != null) {
                line = line.trim();
                if (line.isEmpty()) continue;

                // 2. Extract the brick name
                // Format is: Name,Rotation,X,Y -> We grab everything before the first comma
                int commaIndex = line.indexOf(',');
                if (commaIndex != -1) {
                    String brickName = line.substring(0, commaIndex);

                    // 3. Update the count in the map
                    // If it exists, add 1. If not, set to 1.
                    brickCounts.merge(brickName, 1, Integer::sum);
                }
            }
        } catch (IOException e) {
            System.err.println("Error parsing solution file: " + e.getMessage());
            // Depending on how you want to handle errors, you can throw a RuntimeException here
            return new HashMap<>();
        }

        return brickCounts;
    }

    public Quote requestQuote(HashMap<String, Integer> bricks) throws IOException {

        JsonObject req = new JsonObject();
        for (HashMap.Entry<String, Integer> entry : bricks.entrySet()) {
            req.addProperty(entry.getKey(), entry.getValue());
        }
        JsonObject res = factory.requestQuote(req);

        return new Quote(
                res.get("id").getAsString(),
                res.get("price").getAsDouble(),
                res.get("delay").getAsLong()
        );
    }

    public void confirmOrder(String id) throws IOException {
        factory.confirmOrder(id);
    }

    public Delivery deliveryStatus(String id) throws IOException {

        JsonObject res = factory.deliver(id);
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
