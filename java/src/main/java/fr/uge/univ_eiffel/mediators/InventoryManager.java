package fr.uge.univ_eiffel.mediators;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import fr.uge.univ_eiffel.mediators.legofactory.LegoFactory;
import org.jetbrains.annotations.Contract;
import org.jetbrains.annotations.NotNull;

import java.io.IOException;
import java.io.InputStream;
import java.io.PrintWriter;
import java.sql.*;
import java.util.*;

import static java.sql.Types.NULL;

/** Manages the connection to the local database (MariaDB).
 * Handles catalog updates, stock export for C, and inventory insertions.
 * The code is currently adapted to my local MariaDB database, but i left the
 * SQL dump if you wish to try it for yourself.
 * Fields: The active JDBC Connection. */
public class InventoryManager implements AutoCloseable {

    private Connection connection;



    private InventoryManager(String url, String user, String password) throws Exception {
        connection = DriverManager.getConnection(url, user, password);
    }

    /*
     * ----NOTE----
     * les prix unitaires sont calculés à partir de la formule du serveur Go :
     *     prix = UnitPrice * (PriceDecreaseFactor)^(log2(largeur*hauteur))
     *
     * C'est pour éviter des milliers d'appels API lents tout en donnant au programme C
     * des prix cohérents. Meme si les constantes unitPrice changent dans le serveur Go, la formule
     * reste valide car elle ne sert qu'a l'optimisation du prix dans la partie C.
     */
    public static double computeUnitPrice(int width, int height) {
        double unitPrice = 0.01;
        double factor = 0.9;
        int area = width * height;

        double price = unitPrice * Math.pow(factor, Math.log(area) / Math.log(2));
        return price;
    }

    /** this function will setup the catalog of an empty database,
     * or will update it to its latest version, its takes a few seconds to run
     * Input: An active FactoryClient instance.
     * Output: void (updates DB). */
    public void updateCatalog(@NotNull LegoFactory lf) throws Exception {

        JsonObject cat = lf.catalog();
        JsonArray blocks = cat.getAsJsonArray("blocks");
        JsonArray colors = cat.getAsJsonArray("colors");

        String query = "INSERT INTO CATALOG (width, height, color_hex, color_name, holes) VALUES (?, ?, ?, ?, ?)";

        try (PreparedStatement insertStmt = connection.prepareStatement(query)) {

            for (JsonElement dim : blocks) {
                String[] parts = dim.getAsString().split("-");
                int w = Integer.parseInt(parts[0]);
                int h = Integer.parseInt(parts[1]);
                String holes = "-1";
                if (parts.length == 3) {
                    holes = parts[2];
                }

                for (JsonElement c : colors) {
                    JsonObject color = c.getAsJsonObject();
                    String name = color.get("name").getAsString();
                    String hex = color.get("hex_code").getAsString();

                    try {
                        insertStmt.setInt(1, w);
                        insertStmt.setInt(2, h);
                        insertStmt.setString(3, hex);
                        insertStmt.setString(4, name);
                        insertStmt.setString(5, holes);
                        insertStmt.executeUpdate();
                        System.out.printf("Added %s %d x %d%n", name, w, h);
                    } catch (SQLException e) {
                        // dups are ignored, let DB handle it because it's faster
                        if (e.getErrorCode() != 1062) {
                            throw e;
                        }
                    }
                }
            }
        }
    }

    /** Closes the database connection safely.
     * Input: None.
     * Output: void. */
    @Override
    public void close() {
        try {
            if (connection != null && !connection.isClosed()) {
                connection.close();
                System.out.println("Database connection closed cleanly.");
            }
        } catch (Exception e) {
            System.err.println("Failed to close DB connection: " + e.getMessage());
        }
    }

    /** Dumps the catalog and current stock to a text file for the C program.
     * Uses a view 'catalog_with_stock' to get aggregated quantities.
     * Input: File path to write to (without extension).
     * Output: The full filename including extension. */
    public String exportCatalog(String catPath) throws Exception {

        // count lines
        String countQuery = "SELECT COUNT(*) FROM catalog_with_price_and_stock";
        Statement countStmt = connection.createStatement();
        ResultSet countRs = countStmt.executeQuery(countQuery);

        countRs.next();
        int rowCount = countRs.getInt(1);

        // get data
        String query = "SELECT * FROM catalog_with_price_and_stock";
        Statement stmt = connection.createStatement();
        ResultSet result = stmt.executeQuery(query);

        try (PrintWriter writer = new PrintWriter(new PrintWriter(catPath))) {
            writer.println(rowCount); // first line: number of rows

            while (result.next()) {

                int width = result.getInt("width");
                int height = result.getInt("height");
                String holes = result.getString("holes");
                String hex = result.getString("color_hex");
                double price = result.getDouble("unit_price");
                int stock = result.getInt("stock");

                writer.printf(Locale.US,"%d,%d,%s,%s,%.5f,%d%n", width, height, holes, hex, price, stock);
            }
        }
        return catPath;
    }

    /** Helper to convert hex string to byte array.
     * Used for storing serials and certificates as binary in DB.
     * Input: Hex string.
     * Output: Byte array. */
    public static byte @NotNull [] hexToBytes(@NotNull String hex) {
        int len = hex.length();
        byte[] data = new byte[len / 2];
        for (int i = 0; i < len; i += 2) {
            data[i / 2] = (byte) ((Character.digit(hex.charAt(i), 16) << 4)
                    + Character.digit(hex.charAt(i+1), 16));
        }
        return data;
    }

    public int getCatalogId(String brickName) throws SQLException {
        String[] parts = brickName.split("/");
        if (parts.length != 2) {
            throw new IllegalArgumentException("Invalid brick name format: " + brickName);
        }
        String sizePart = parts[0];
        String hex = parts[1];

        String[] sizeTokens = sizePart.split("-");
        if (sizeTokens.length < 2) {
            throw new IllegalArgumentException("Invalid brick size in name: " + brickName);
        }
        int width = Integer.parseInt(sizeTokens[0]);
        int height = Integer.parseInt(sizeTokens[1]);

        String holes = "-1";
        if (sizeTokens.length > 2) {
            holes = sizeTokens[2];
        }

        String selectSql = "SELECT id_catalogue FROM CATALOG WHERE width = ? AND height = ? AND holes = ? AND color_hex = ?";
        try (PreparedStatement stmt = connection.prepareStatement(selectSql)) {
            stmt.setInt(1, width);
            stmt.setInt(2, height);
            stmt.setString(3, holes);
            stmt.setString(4, hex);

            try (ResultSet rs = stmt.executeQuery()) {
                    if (rs.next()) {
                        return rs.getInt("id_catalogue");
                    } else {
                        throw new SQLException("No matching catalog entry found for brick: " + brickName);
                    }
            }
        }
    }

    /**
     * Inserts a new confirmed tiling into the TILING table.
     *
     * @param pavagePathTxt the tiling text content
     * @param imageId the associated image ID
     * @return the generated tiling ID (pavage_id)
     * @throws SQLException if a database access error occurs or the insertion fails
     */
    public int newConfirmedTiling(String pavagePathTxt, Integer imageId) throws SQLException {
        // Vérifier que l'image_id existe si il n'est pas null
        if (imageId != null) {
            String checkImageSql = "SELECT COUNT(*) FROM IMAGE WHERE image_id = ?";
            try (PreparedStatement checkStmt = connection.prepareStatement(checkImageSql)) {
                checkStmt.setInt(1, imageId);
                try (ResultSet rs = checkStmt.executeQuery()) {
                    if (rs.next() && rs.getInt(1) == 0) {
                        throw new SQLException("L'image_id " + imageId + " n'existe pas dans la table IMAGE");
                    }
                }
            }
        }

        String insertSql = "INSERT INTO TILLING (pavage_txt, image_id) VALUES (?, ?)";
        String pavageName = new java.io.File(pavagePathTxt).getName();

        try (PreparedStatement stmt = connection.prepareStatement(insertSql, Statement.RETURN_GENERATED_KEYS)) {
            stmt.setString(1, pavageName);
            if (imageId != null) {
                stmt.setInt(2, imageId);
            } else {
                stmt.setNull(2, java.sql.Types.INTEGER);
            }
            stmt.executeUpdate();

            try (ResultSet rs = stmt.getGeneratedKeys()) {
                if (rs.next()) {
                    return rs.getInt(1);
                } else {
                    throw new SQLException("Failed to retrieve generated tiling ID");
                }
            }
        }
    }

    public List<Integer> getAllCatalogIds() throws SQLException {
        List<Integer> ids = new ArrayList<>();
        try (Statement stmt = connection.createStatement();
             ResultSet rs = stmt.executeQuery("SELECT id_catalogue FROM CATALOG")) {
            while (rs.next()) {
                ids.add(rs.getInt("id_catalogue"));
            }
        }
        return ids;
    }


    /** Adds a newly delivered brick into the inventory table.
     * Links the brick to the correct catalog entry ID.
     * @param brick (name, serial, certificate).
     * @param tilingID The pavage ID this brick is associated with.
     * Output: True if successful. */
    public boolean add(@NotNull Brick brick, Integer tilingID) throws SQLException {
        // Parse the brick name, ex : "1-1/4d4c52" or "1-1-0123/4d4c52"
        Integer catalogId = getCatalogId(brick.name());

        // Insert the brick into inventory
        String insertSql = "INSERT INTO INVENTORY (certificate, serial_num, pavage_id, id_catalogue) VALUES (?, ?, ?, ?)";

        try (PreparedStatement stmt = connection.prepareStatement(insertSql)) {
            stmt.setString(1, brick.certificate());
            stmt.setString(2, brick.serial());
            if(tilingID == null) {
                stmt.setNull(3, NULL);
            } else {
                stmt.setInt(3, tilingID);
            }
            stmt.setInt(4, catalogId);
            stmt.executeUpdate();
        }
        return true;
    }

    // java
    public void addBatch(List<Brick> bricks, Integer tilingID) throws SQLException {
        String insertSql = "INSERT INTO INVENTORY (certificate, serial_num, pavage_id, id_catalogue) VALUES (?, ?, ?, ?)";

        connection.setAutoCommit(false);

        // caches to avoid DB roundtrips
        Map<String, Integer> catalogCache = new HashMap<>();      // brick.name() -> id_catalogue
        Map<Integer, Double> priceCache = new HashMap<>();       // id_catalogue -> unit price

        final int BATCH_SIZE = 1000;
        try (PreparedStatement stmt = connection.prepareStatement(insertSql)) {
            int count = 0;

            for (Brick brick : bricks) {
                String name = brick.name();

                // get or cache catalog id
                Integer catalogId = catalogCache.get(name);
                if (catalogId == null) {
                    catalogId = getCatalogId(name); // one DB call only on first occurrence
                    catalogCache.put(name, catalogId);
                }

                // compute or reuse unit price locally (avoids per-brick SQL price call)
                Double unitPrice = priceCache.get(catalogId);
                if (unitPrice == null) {
                    // parse width and height from name like "W-H[/holes]/hex"
                    String[] parts = name.split("/");
                    String[] sizeTokens = parts[0].split("-");
                    int width = Integer.parseInt(sizeTokens[0]);
                    int height = Integer.parseInt(sizeTokens[1]);
                }

                stmt.setString(1, brick.certificate());
                stmt.setString(2, brick.serial());
                if (tilingID == null) {
                    stmt.setNull(3, NULL);
                } else {
                    stmt.setInt(3, tilingID);
                }
                stmt.setInt(4, catalogId);

                stmt.addBatch();

                if (++count % BATCH_SIZE == 0) {
                    stmt.executeBatch();
                }
            }

            stmt.executeBatch();
            connection.commit();
            System.out.println("Batch insert complete: " + bricks.size() + " bricks added.");
        } catch (SQLException e) {
            connection.rollback();
            throw e;
        } finally {
            connection.setAutoCommit(true);
        }
    }



    //---------------Restock methode-----------------

    /**
     * Retrieves the IDs of orders placed within the last specified number of days.
     *
     * @param days the number of days in the past to look for orders (e.g., 7 for the last 7 days)
     * @return a List of order IDs created within the specified time period
     * @throws SQLException if a database access error occurs or the SQL query fails
     */
    public List<Integer> getOrders(int days) throws SQLException {
        System.out.println("Getting orders id placed within the last " + days + " days..." );
        List<Integer> ordersId = new ArrayList<>();
        String selectSql = "SELECT order_id FROM ORDER_BILL WHERE created_at >= NOW() - INTERVAL ? DAY";
        try (PreparedStatement stmt = connection.prepareStatement(selectSql)){
            stmt.setInt(1, days); // passe le nombre de jours
            try(ResultSet rs = stmt.executeQuery()) {
                while (rs.next()) {
                    ordersId.add(rs.getInt("order_id"));
                }
                return ordersId;
            }
        }
    }

    /**
     * Retrieves the inventory piece IDs associated with a given order.
     *
     * @param idOrder the identifier of the order for which to retrieve inventory pieces
     * @return a List of inventory piece IDs linked to the specified order;
     *         the list is empty if no pieces are found
     * @throws RuntimeException if a database access error occurs or the SQL query fails
     */
    public List<RestockManager.Piece> getOrderPieces(int idOrder){
        System.out.println("\t-> Getting order " + idOrder + " pieces..." );
        List<RestockManager.Piece> piecesId = new ArrayList<>();
        String selectSql = "SELECT * FROM INVENTORY " +
                "JOIN TILLING ON TILLING.pavage_id = INVENTORY.pavage_id " +
                "JOIN contain ON contain.pavage_id = TILLING.pavage_id " +
                "WHERE contain.order_id = ? ";
        try (PreparedStatement stmt = connection.prepareStatement(selectSql)){
            stmt.setInt(1, idOrder);
            try(ResultSet rs = stmt.executeQuery()) {
                while (rs.next()) {
                    piecesId.add(new RestockManager.Piece(rs.getInt("id_inventory"), rs.getInt("pavage_id"), rs.getInt("id_catalogue")));
                }
                return piecesId;
            }
        } catch (SQLException e) {
            throw new RuntimeException(e);
        }
    }


    /**
     * Retrieves the current stock level for ALL catalog items using the DB View.
     * Returns 0 for items not in stock (instead of missing them from the map).
     */
    public Map<Integer, Integer> getFullStock() throws SQLException {
        Map<Integer, Integer> stock = new HashMap<>();
        // Query the view directly
        String sql = "SELECT id_catalogue, stock FROM catalog_with_price_and_stock";

        try (Statement stmt = connection.createStatement();
             ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                stock.put(rs.getInt("id_catalogue"), rs.getInt("stock"));
            }
        }
        return stock;
    }

    /**
     * Retrieves the brick type name associated with a given catalog identifier.
     *
     * This method queries the catalog database to obtain the brick dimensions
     * (width and height) and its color, then formats them into a standardized
     * brick type name.
     *
     * @param id the catalog identifier of the brick
     * @return a String representing the brick type name in the format
     *         "width-height/color_hex"
     * @throws RuntimeException if a database access error occurs or the SQL query fails
     */
    public String getBrickTypeName(int id){
        System.out.println("Getting brick type name..." );
        StringBuilder brickTypeName = new StringBuilder();
        String selectSql = "SELECT height, width, color_hex FROM CATALOG " +
                "WHERE CATALOG.id_catalogue = ?";
        try (PreparedStatement stmt = connection.prepareStatement(selectSql)){
            stmt.setInt(1, id);
            try(ResultSet rs = stmt.executeQuery()) {
                while (rs.next()) {
                    brickTypeName.append(rs.getInt("width") + "-" + rs.getInt("height") + "/" +  rs.getString("color_hex"));
                }
                return brickTypeName.toString();
            }
        } catch (SQLException e) {
            throw new RuntimeException(e);
        }
    }



    /** Factory method to create an instance from a properties file.
     * Input: Filename (e.g., "config.properties").
     * Output: Initialized InventoryManager connected to DB. */
    @Contract("_ -> new")
    public static @NotNull InventoryManager makeFromProps(String file) {
        Properties props = new Properties();

        try (InputStream input = InventoryManager.class.getClassLoader().getResourceAsStream(file)) {
            if (input == null) {
                throw new RuntimeException("Properties file '" + file + "' not found.");
            }

            props.load(input);

            String url = props.getProperty("DB_URL");
            String user = props.getProperty("DB_USER");
            String password = props.getProperty("DB_PASSWORD");

            if (url == null || user == null || password == null) {
                throw new RuntimeException("One of the logins is missing or incorrect in properties file.");
            }

            return new InventoryManager(url, user, password);

        } catch (IOException e) {
            throw new RuntimeException(e);
        } catch (Exception e) {
            throw new RuntimeException("Failed to connect to database", e);
        }
    }

    /**
     * Adds a restocking history entry and its associated inventory entries.
     * Uses batch processing and local caching to optimize performance.
     *
     * @param stockToRefill a map associating brick type names with quantities
     * to be restocked, formatted as "width-height/color_hex"
     * @return true if the restock history is successfully added
     * @throws RuntimeException if a database access error occurs during the process
     */
    public boolean addRestockHistory(Map<String, Integer> stockToRefill) {
        System.out.println("Adding restock history (Batch Mode)...");

        String insertStockEntrySql = "INSERT INTO STOCK_ENTRY (date_stock) VALUES (NOW())";
        String insertEntrySql = "INSERT INTO `entry` (id_catalogue, id_stock_entry, quantity, total_price) VALUES (?, ?, ?, ?)";

        final int BATCH_SIZE = 1000;

        // Caches to avoid DB roundtrips inside the loop
        Map<String, Integer> catalogCache = new HashMap<>();      // brickName -> id_catalogue
        Map<Integer, Double> priceCache = new HashMap<>();        // id_catalogue -> unit price

        try {
            connection.setAutoCommit(false); // Start transaction

            // 1. Create the main Stock Entry
            int stockEntryId;
            try (PreparedStatement stmt = connection.prepareStatement(insertStockEntrySql, Statement.RETURN_GENERATED_KEYS)) {
                stmt.executeUpdate();
                try (ResultSet rs = stmt.getGeneratedKeys()) {
                    if (rs.next()) {
                        stockEntryId = rs.getInt(1);
                    } else {
                        throw new SQLException("Cannot get the id_stock_entry");
                    }
                }
            }

            // 2. Batch Insert the Entries
            try (PreparedStatement stmt = connection.prepareStatement(insertEntrySql)) {
                int count = 0;

                for (Map.Entry<String, Integer> entry : stockToRefill.entrySet()) {
                    String brickName = entry.getKey();
                    int quantity = entry.getValue();

                    // Resolve Catalog ID (Cache -> DB)
                    Integer idCatalog = catalogCache.get(brickName);
                    if (idCatalog == null) {
                        idCatalog = getCatalogId(brickName);
                        catalogCache.put(brickName, idCatalog);
                    }

                    // Resolve Unit Price (Cache -> Local Calculation)
                    // We calculate locally to avoid N+1 SQL calls to 'getUnitPrice'
                    Double unitPrice = priceCache.get(idCatalog);
                    if (unitPrice == null) {
                        // Parse dimensions from name "width-height/hex"
                        String[] parts = brickName.split("/");
                        String[] sizeTokens = parts[0].split("-");
                        int width = Integer.parseInt(sizeTokens[0]);
                        int height = Integer.parseInt(sizeTokens[1]);

                        unitPrice = computeUnitPrice(width, height);
                        priceCache.put(idCatalog, unitPrice);
                    }

                    stmt.setInt(1, idCatalog);       // id_catalogue
                    stmt.setInt(2, stockEntryId);    // id_stock_entry
                    stmt.setInt(3, quantity);        // quantity
                    stmt.setDouble(4, quantity * unitPrice); // total_price

                    stmt.addBatch();

                    if (++count % BATCH_SIZE == 0) {
                        stmt.executeBatch();
                    }
                }

                // Execute any remaining in the batch
                stmt.executeBatch();
            }

            connection.commit();
            System.out.println("Restock history added successfully.");
            return true;

        } catch (SQLException e) {
            try {
                connection.rollback();
            } catch (SQLException ex) {
                e.addSuppressed(ex);
            }
            throw new RuntimeException("Transaction failed during restock history add", e);
        } finally {
            try {
                connection.setAutoCommit(true);
            } catch (SQLException e) {
                e.printStackTrace();
            }
        }
    }

    /**
     * Reserves existing stock for a specific tiling project.
     * Tries to update 'pavage_id' to the given tilingID for the requested quantity of each brick.
     *
     * @param needed A map of <CatalogID, QuantityNeeded>.
     * @param tilingID The ID of the tiling project to assign these bricks to.
     */
    public void reserveStockForTiling(Map<Integer, Integer> needed, int tilingID) {
        System.out.println("Reserving existing stock for tiling " + tilingID + "...");
        String updateSql = "UPDATE INVENTORY SET pavage_id = ? WHERE id_catalogue = ? AND pavage_id IS NULL LIMIT ?";

        try (PreparedStatement stmt = connection.prepareStatement(updateSql)) {
            for (Map.Entry<Integer, Integer> entry : needed.entrySet()) {
                int catalogId = entry.getKey();
                int amountToReserve = entry.getValue();

                stmt.setInt(1, tilingID);
                stmt.setInt(2, catalogId);
                stmt.setInt(3, amountToReserve);

                int reservedCount = stmt.executeUpdate();

            }
        } catch (SQLException e) {
            throw new RuntimeException("Error reserving stock for tiling " + tilingID, e);
        }
    }

    private double getUnitPrice(int idCatalog) {
        String sqlPrice = "SELECT calculate_brick_price(0.01, 0.9, width, height) AS price " +
                "FROM CATALOG WHERE id_catalogue = ?";

        try (PreparedStatement priceStmt = connection.prepareStatement(sqlPrice)) {
            priceStmt.setInt(1, idCatalog);
            ResultSet rs = priceStmt.executeQuery();

            if (rs.next()) {
                return rs.getDouble("price");
            } else {
                throw new SQLException("Catalog ID not found: " + idCatalog);
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error calculating unit price", e);
        }
    }
}
