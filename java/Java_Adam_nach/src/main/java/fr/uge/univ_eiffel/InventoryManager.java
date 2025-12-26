package fr.uge.univ_eiffel;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;

import java.io.IOException;
import java.io.InputStream;
import java.io.PrintWriter;
import java.sql.*;
import java.util.Properties;

/** Manages the connection to the local database (MariaDB).
 * Handles catalog updates, stock export for C, and inventory insertions.
 * The code is currently adapted to my local MariaDB database, but i left the
 * SQL dump if you wish to try it for yourself.
 * Fields: The active JDBC Connection. */
public class InventoryManager {

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
    public void updateCatalog(FactoryClient fc) throws Exception {

        JsonObject cat = fc.catalog();
        JsonArray blocks = cat.getAsJsonArray("blocks");
        JsonArray colors = cat.getAsJsonArray("colors");

        String query = "INSERT INTO catalog (width, height, holes, name, color_hex, unit_price) VALUES (?, ?, ?, ?, ?, ?)";

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
                        insertStmt.setString(3, holes);
                        insertStmt.setString(4, name);
                        insertStmt.setString(5, hex);
                        insertStmt.setDouble(6, computeUnitPrice(w, h));
                        insertStmt.executeUpdate();
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
    public void close() {
        try {
            if (connection != null && !connection.isClosed()) {
                connection.close();
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    /** Dumps the catalog and current stock to a text file for the C program.
     * Uses a view 'catalog_with_stock' to get aggregated quantities.
     * Input: File path to write to (without extension).
     * Output: The full filename including extension. */
    public String exportCatalog(String catPath) throws Exception {
        String query = "SELECT width, height, holes, color_hex, unit_price, stock FROM catalog_with_stock";
        Statement stmt = connection.createStatement();
        ResultSet result = stmt.executeQuery(query);

        // we count the number of rows, which will be placed on top of the catalog txt file
        // moves cursor to end to get count, then resets
        result.last();
        int rowCount = result.getRow();
        result.beforeFirst();

        try (PrintWriter writer = new PrintWriter(new PrintWriter(catPath))) {
            writer.println(rowCount); // first line: number of rows

            while (result.next()) {

                int width = result.getInt("width");
                int height = result.getInt("height");
                String holes = result.getString("holes");
                String hex = result.getString("color_hex");
                double price = result.getDouble("unit_price");
                int stock = result.getInt("stock");

                writer.printf("%d,%d,%s,%s,%.5f,%d%n", width, height, holes, hex, price, stock);
            }
        }
        return catPath + ".txt";
    }
    /** Factory method to create an instance from a properties file.
     * Input: Filename (e.g., "config.properties").
     * Output: Initialized InventoryManager connected to DB. */
    public static InventoryManager makeFromProps(String file) {
        Properties props = new Properties();

        try (InputStream input = InventoryManager.class.getClassLoader()
                .getResourceAsStream(file)) {

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

    /** Helper to convert hex string to byte array.
     * Used for storing serials and certificates as binary in DB.
     * Input: Hex string.
     * Output: Byte array. */
    private static byte[] hexToBytes(String hex) {
        int len = hex.length();
        byte[] data = new byte[len / 2];
        for (int i = 0; i < len; i += 2) {
            data[i / 2] = (byte) ((Character.digit(hex.charAt(i), 16) << 4)
                    + Character.digit(hex.charAt(i+1), 16));
        }
        return data;
    }

    /** Adds a newly delivered brick into the inventory table.
     * Links the brick to the correct catalog entry ID.
     * Input: Brick record (name, serial, certificate).
     * Output: True if successful. */
    public boolean add(Brick brick) throws SQLException {
        // Parse the brick name, ex : "1-1/4d4c52" or "1-1-0123/4d4c52"
        String[] parts = brick.name().split("/");
        if (parts.length != 2) {
            throw new IllegalArgumentException("Invalid brick name format: " + brick.name());
        }
        String sizePart = parts[0];
        String hex = parts[1];

        String[] sizeTokens = sizePart.split("-");
        if (sizeTokens.length < 2) {
            throw new IllegalArgumentException("Invalid brick size in name: " + brick.name());
        }
        int width = Integer.parseInt(sizeTokens[0]);
        int height = Integer.parseInt(sizeTokens[1]);
        String holes = "-1";
        if (sizeTokens.length > 2) {
            holes = sizeTokens[2];
        }

        String selectSql = "SELECT id_catalog FROM catalog WHERE width = ? AND height = ? AND holes = ? AND color_hex = ?";
        Integer catalogId = null;
        try (PreparedStatement stmt = connection.prepareStatement(selectSql)) {
            stmt.setInt(1, width);
            stmt.setInt(2, height);
            stmt.setString(3, holes);
            stmt.setString(4, hex);
            try (ResultSet rs = stmt.executeQuery()) {
                if (rs.next()) {
                    catalogId = rs.getInt("id_catalog");
                } else {
                    throw new SQLException("No matching catalog entry found for brick: " + brick.name());
                }
            }
        }

        // Insert the brick into inventory
        String insertSql = "INSERT INTO inventory (serial_num, catalog_id, certificate, is_used) VALUES (?, ?, ?, ?)";

        byte[] certBytes = hexToBytes(brick.certificate());
        byte[] serialBytes = hexToBytes(brick.serial());

        try (PreparedStatement stmt = connection.prepareStatement(insertSql)) {
            stmt.setBytes(1, serialBytes);
            stmt.setInt(2, catalogId);
            stmt.setBytes(3, certBytes);
            stmt.setBoolean(4, false);
            stmt.executeUpdate();
        }
        return true;
    }
}
