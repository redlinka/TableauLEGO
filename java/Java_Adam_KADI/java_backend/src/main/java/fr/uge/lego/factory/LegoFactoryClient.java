package fr.uge.lego.factory;

import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;
import fr.uge.lego.factory.dto.*;
import fr.uge.lego.factory.pow.ProofOfWorkSolver;

import java.io.*;
import java.lang.reflect.Type;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Map;

/**
 * Minimal HTTP client using HttpURLConnection for the Lego factory REST API.
 */
public final class LegoFactoryClient {

    private static final Gson GSON = new Gson();
    private final FactoryConfig config;

    public LegoFactoryClient(FactoryConfig config) {
        this.config = config;
    }

    private HttpURLConnection open(String path, String method, boolean auth) throws IOException {
        URL url = new URL(config.baseUrl() + path);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod(method);
        conn.setRequestProperty("Accept", "application/json");
        if (auth) {
            conn.setRequestProperty("X-Email", config.email());
            conn.setRequestProperty("X-Secret-Key", config.secretKey());
        }
        return conn;
    }

    private String readBody(HttpURLConnection conn) throws IOException {
        InputStream in = conn.getResponseCode() >= 400 ? conn.getErrorStream() : conn.getInputStream();
        if (in == null) {
            return "";
        }
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(in, StandardCharsets.UTF_8))) {
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line);
            }
            return sb.toString();
        }
    }

    public String ping() throws IOException {
        HttpURLConnection conn = open("/ping", "GET", true);
        int code = conn.getResponseCode();
        String body = readBody(conn);
        return "HTTP " + code + " " + body;
    }

    public BillingBalance getBalance() throws IOException {
        HttpURLConnection conn = open("/billing/balance", "GET", true);
        int code = conn.getResponseCode();
        if (code != 200) {
            throw new IOException("Unexpected status " + code);
        }
        String body = readBody(conn);
        return GSON.fromJson(body, BillingBalance.class);
    }

    public Challenge fetchBillingChallenge() throws IOException {
        HttpURLConnection conn = open("/billing/challenge", "GET", true);
        int code = conn.getResponseCode();
        if (code != 200) {
            throw new IOException("Unexpected status " + code);
        }
        String body = readBody(conn);
        return GSON.fromJson(body, Challenge.class);
    }

    public ChallengeAnswerResult sendBillingChallengeAnswer(Challenge challenge, byte[] answer) throws IOException {
        HttpURLConnection conn = open("/billing/challenge-answer", "POST", true);
        conn.setDoOutput(true);
        String json = GSON.toJson(Map.of(
                "data_prefix", challenge.data_prefix,
                "hash_prefix", challenge.hash_prefix,
                "answer", ProofOfWorkSolver.toHex(answer)
        ));
        try (Writer out = new OutputStreamWriter(conn.getOutputStream(), StandardCharsets.UTF_8)) {
            out.write(json);
        }
        int code = conn.getResponseCode();
        readBody(conn); // ignore body
        return new ChallengeAnswerResult(code);
    }

    public Map<String, Object> getCatalogRaw() throws IOException {
        HttpURLConnection conn = open("/catalog", "GET", false);
        int code = conn.getResponseCode();
        if (code != 200) {
            throw new IOException("Unexpected status " + code);
        }
        String body = readBody(conn);
        Type t = new TypeToken<Map<String, Object>>(){}.getType();
        return GSON.fromJson(body, t);
    }

    public QuoteRequestResult requestQuote(Map<String, Integer> items) throws IOException {
        HttpURLConnection conn = open("/ordering/quote-request", "POST", true);
        conn.setDoOutput(true);
        String json = GSON.toJson(items);
        try (Writer out = new OutputStreamWriter(conn.getOutputStream(), StandardCharsets.UTF_8)) {
            out.write(json);
        }
        int code = conn.getResponseCode();
        if (code != 200) {
            throw new IOException("Unexpected status " + code);
        }
        String body = readBody(conn);
        return GSON.fromJson(body, QuoteRequestResult.class);
    }

    public void confirmOrder(long quoteId) throws IOException {
        HttpURLConnection conn = open("/ordering/order/" + quoteId, "POST", true);
        int code = conn.getResponseCode();
        if (code != 200) {
            throw new IOException("Unexpected status " + code);
        }
        readBody(conn);
    }

    public DeliveryResult requestDelivery(long quoteId) throws IOException {
        HttpURLConnection conn = open("/ordering/deliver/" + quoteId, "GET", true);
        int code = conn.getResponseCode();
        if (code != 200) {
            throw new IOException("Unexpected status " + code);
        }
        String body = readBody(conn);
        return GSON.fromJson(body, DeliveryResult.class);
    }
}
