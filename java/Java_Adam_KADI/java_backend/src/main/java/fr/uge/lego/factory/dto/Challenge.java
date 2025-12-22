package fr.uge.lego.factory.dto;

/**
 * Billing challenge for proof-of-work.
 */
public final class Challenge {
    public String data_prefix;
    public String hash_prefix;
    public String reward;

    @Override
    public String toString() {
        return "Challenge{" +
                "data_prefix='" + data_prefix + '\'' +
                ", hash_prefix='" + hash_prefix + '\'' +
                ", reward='" + reward + '\'' +
                '}';
    }
}
