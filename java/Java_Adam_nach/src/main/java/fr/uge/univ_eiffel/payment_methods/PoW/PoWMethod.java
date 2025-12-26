package fr.uge.univ_eiffel.payment_methods.PoW;

import java.io.IOException;
import java.util.HexFormat;

import com.google.gson.Gson;
import fr.uge.univ_eiffel.FactoryClient;
import fr.uge.univ_eiffel.payment_methods.PaymentMethod;

/**
 * One of the methods to refill the prepaid account on the Lego factory.
 * Uses computational power (Proof of Work) to solve SHA-256 challenges in exchange for credits.
 * Fields: The solver instance, the API client, and a Gson instance.
 */
public class PoWMethod implements PaymentMethod {

    public static final ProofOfWorkSolver POW_SOLVER = new ProofOfWorkSolver("SHA-256");
    private final FactoryClient client;
    private Gson gson = new Gson();

    public record Challenge(String data_prefix, String hash_prefix) {}
    public record ChallengeAnswer(String data_prefix, String hash_prefix, String answer) {}

    public PoWMethod(FactoryClient client) {
        this.client = client;
    }

    /**
     * Retrieves a new crypto-puzzle from the factory API.
     * Input: None.
     * Output: A Challenge record containing the data and hash prefixes.
     */
    public Challenge fetchChallenge() throws IOException {
        Challenge challenge = gson.fromJson(client.billingChallenge(), Challenge.class);
        System.err.println("Received PoW challenge: " + challenge);
        return challenge;
    }

    /**
     * Crunches the numbers to find the correct suffix.
     * Uses the ProofOfWorkSolver to brute-force the SHA-256 hash.
     * Input: The Challenge object to solve.
     * Output: A ChallengeAnswer containing the solution hex string.
     */
    public ChallengeAnswer solveChallenge(Challenge challenge) {
        var startTime = System.nanoTime();
        // convert hex strings to bytes for solver
        byte[] dataPrefix = HexFormat.of().parseHex(challenge.data_prefix());
        byte[] hashPrefix = HexFormat.of().parseHex(challenge.hash_prefix());
        byte[] solved = POW_SOLVER.solve(dataPrefix, hashPrefix);
        System.err.println("Challenge solved in " + (System.nanoTime() - startTime)/1e9 + " seconds");
        ChallengeAnswer answer = new ChallengeAnswer(challenge.data_prefix(), challenge.hash_prefix(), HexFormat.of().formatHex(solved));
        return answer;
    }

    /**
     * Sends the calculated solution back to the factory to claim the reward.
     * Input: The completed ChallengeAnswer.
     * Output: void (throws IOException if rejected).
     */
    public void submitAnswer(ChallengeAnswer solution) throws IOException {
        client.billingChallengeAnswer(solution.data_prefix(), solution.hash_prefix(), solution.answer());
    }

    /**
     * Main loop that mines credits until the requested amount is reached.
     * Keeps solving challenges one by one.
     * Input: The target amount of money needed (double).
     * Output: void (prints progress to console).
     */
    public void pay(double amount) throws IOException {
        double moneyMade = 0;
        while (moneyMade < amount) {

            // we fetch the challenge
            Challenge challenge = fetchChallenge();
            // we try to solve the challenge
            ChallengeAnswer answer = solveChallenge(challenge);
            // we submit the answer
            submitAnswer(answer);
            // we show the current balance for convenience
            System.out.println("Current account balance: " + client.balance());

            // assuming 1 euro per solution, though this might vary
            moneyMade++;
        }
        System.out.println("Payment made: " + moneyMade);
    }
}
