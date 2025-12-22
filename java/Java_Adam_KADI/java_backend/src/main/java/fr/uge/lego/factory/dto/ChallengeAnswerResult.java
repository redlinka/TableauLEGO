package fr.uge.lego.factory.dto;

/**
 * Simple wrapper for HTTP status code of challenge-answer.
 */
public final class ChallengeAnswerResult {
    private final int statusCode;

    public ChallengeAnswerResult(int statusCode) {
        this.statusCode = statusCode;
    }

    public int statusCode() { return statusCode; }

    @Override
    public String toString() {
        return "HTTP " + statusCode;
    }
}
