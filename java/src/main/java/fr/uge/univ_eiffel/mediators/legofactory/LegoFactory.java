package fr.uge.univ_eiffel.mediators.legofactory;

import com.google.gson.JsonObject;
import java.io.IOException;

/**
 * Interface defining the contract for interacting with the Lego Factory.
 * This allows us to swap between a Real HTTP Client, a Dummy Client for testing,
 * or a different API implementation later.
 */
public interface LegoFactory {

    // Connectivity
    String ping() throws IOException;

    // Info
    JsonObject catalog() throws IOException;
    JsonObject production() throws IOException;
    String signaturePublicKey() throws IOException;

    // Billing
    double balance() throws IOException;
    JsonObject billingChallenge() throws IOException;
    void billingChallengeAnswer(String dataPrefix, String hashPrefix, String answer) throws IOException;

    // Verification
    boolean verify(String name, String serial, String certificate);

    // Ordering
    JsonObject requestQuote(JsonObject bricksRequest) throws IOException;
    void confirmOrder(String quoteId) throws IOException;
    JsonObject deliver(String quoteId) throws IOException;
}