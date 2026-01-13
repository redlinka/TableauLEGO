package fr.uge.univ_eiffel.mediators.legofactory;

import com.google.gson.Gson;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;

import java.io.IOException;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/** Handles HTTP communication with the Lego Factory API.
 * Acts as the bridge between the app and the external server.
 * Fields: API URL, user email, and secret key. */
public class HttpFactoryClient implements LegoFactory {

    private final String BASE_URL;
    private final String EMAIL;
    private final String API_KEY;
    private final Gson gson = new Gson();

    public HttpFactoryClient(String url, String email, String apiKey) {
        this.BASE_URL = url;
        this.EMAIL = email;
        this.API_KEY = apiKey;
    }

    /** Helper method to set up an HttpURLConnection with headers.
     * Input: Endpoint path and HTTP method (GET/POST).
     * Output: Configured HttpURLConnection instance. */
    private HttpURLConnection connect(String endpoint, String method) throws IOException {
        var url = new URL(BASE_URL + endpoint);
        var connection = (HttpURLConnection) url.openConnection();

        connection.setRequestMethod(method);
        connection.setDoOutput(true);

        // factory api sometimes redirects on order completion
        connection.setInstanceFollowRedirects(true);
        connection.addRequestProperty("X-Email", EMAIL);
        connection.addRequestProperty("X-Secret-Key", API_KEY);
        connection.addRequestProperty("Content-Type", "application/json");
        return connection;
    }

    /** Helper method to perform a GET request.
     * Input: Endpoint path (e.g. "/ping").
     * Output: Raw response body as a String. */
    private String get(String endpoint) throws IOException {

        var connection = connect(endpoint, "GET");

        int status = connection.getResponseCode();
        if (status != 200) {
            throw new IOException("GET " + endpoint + " failed with status " + status);
        }
        return new String(connection.getInputStream().readAllBytes(), StandardCharsets.UTF_8);
    }

    /** Helper method to perform a POST request with JSON payload.
     * handles automatic redirects for 302 status codes.
     * Input: Endpoint path and JSON string body.
     * Output: Raw response body as a String. */
    private String post(String endpoint, String jsonBody) throws IOException {

        var connection = connect(endpoint, "POST");

        if (jsonBody != null && !jsonBody.isEmpty()) {
            connection.getOutputStream().write(jsonBody.getBytes(StandardCharsets.UTF_8));
        }

        int status = connection.getResponseCode();

        // accepts 2xx and 3xx as success to handle redirects gracefully
        if (status >= 200 && status < 400) {
            return new String(connection.getInputStream().readAllBytes(), StandardCharsets.UTF_8);
        } else {
            // read error stream for better debugging
            String errorMsg = "POST " + endpoint + " failed with status " + status;
            try {
                if (connection.getErrorStream() != null) {
                    String errContent = new String(connection.getErrorStream().readAllBytes(), StandardCharsets.UTF_8);
                    errorMsg += " Body: " + errContent;
                }
            } catch (Exception e) { /* ignore */ }
            throw new IOException(errorMsg);
        }
    }

    /** Checks connectivity and credentials.
     * Input: None.
     * Output: Server response (usually "pong" or similar). */
    @Override
    public String ping() throws IOException {
        return get("/ping");
    }

    /** Fetches the full list of available bricks and colors.
     * Input: None.
     * Output: JsonObject containing the catalog data. */
    @Override
    public JsonObject catalog() throws IOException {
        return JsonParser.parseString(get("/catalog")).getAsJsonObject();
    }

    /** Gets the total production stats from the factory.
     * Input: None.
     * Output: JsonObject with production numbers. */
    @Override
    public JsonObject production() throws IOException {
        return JsonParser.parseString(get("/production")).getAsJsonObject();
    }

    /** Retrieves the public key for verifying brick certificates.
     * Input: None.
     * Output: The Ed25519 public key as a String. */
    @Override
    public String signaturePublicKey() throws IOException {
        return get("/signature-public-key");
    }

    /** Checks the current prepaid account balance.
     * Input: None.
     * Output: The balance amount as a double. */
    @Override
    public double balance() throws IOException {

        String json = get("/billing/balance");
        return JsonParser.parseString(json).getAsJsonObject().get("balance").getAsDouble();
    }

    /** Fetches a Proof of Work challenge to refill credits.
     * Input: None.
     * Output: JsonObject containing data_prefix and hash_prefix. */
    @Override
    public JsonObject billingChallenge() throws IOException {

        return JsonParser.parseString(get("/billing/challenge")).getAsJsonObject();
    }

    /** Submits a solved PoW challenge to earn credits.
     * Input: The challenge prefixes and the computed answer.
     * Output: void (throws IOException if rejected). */
    @Override
    public void billingChallengeAnswer(String dataPrefix, String hashPrefix, String answer) throws IOException {

        JsonObject payload = new JsonObject();
        payload.addProperty("data_prefix", dataPrefix);
        payload.addProperty("hash_prefix", hashPrefix);
        payload.addProperty("answer", answer);

        post("/billing/challenge-answer", gson.toJson(payload));
    }

    /** Verifies the authenticity of a brick's certificate.
     * Input: Brick name, serial number, and certificate signature.
     * Output: True if valid, False otherwise. */
    @Override
    public boolean verify(String name, String serial, String certificate) {

        JsonObject payload = new JsonObject();
        payload.addProperty("name", name);
        payload.addProperty("serial", serial);
        payload.addProperty("certificate", certificate);

        try {
            post("/verify", gson.toJson(payload));
            return true;

        } catch (IOException e) {
            return false;
        }
    }

    /** Sends a shopping list to the factory to get a price quote.
     * Input: JsonObject mapping brick names to quantities.
     * Output: JsonObject containing quote ID and price. */
    @Override
    public JsonObject requestQuote(JsonObject bricksRequest) throws IOException {

        String json = post("/ordering/quote-request", gson.toJson(bricksRequest));
        return JsonParser.parseString(json).getAsJsonObject();
    }

    /** Confirms a quote and places the order.
     * Input: The quote ID received from requestQuote.
     * Output: void. */
    @Override
    public void confirmOrder(String quoteId) throws IOException {

        post("/ordering/order/" + quoteId, "");
    }

    /** Polls the delivery status of an order.
     * Input: The quote ID.
     * Output: JsonObject with status and list of built bricks. */
    @Override
    public JsonObject deliver(String quoteId) throws IOException {

        String json = get("/ordering/deliver/" + quoteId);
        return JsonParser.parseString(json).getAsJsonObject();
    }
}

