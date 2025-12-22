package fr.uge.lego.factory.dto;

/**
 * Returned by POST /ordering/quote-request.
 */
public final class QuoteRequestResult {
    public long id;
    public String price;
    public long delay;

    @Override
    public String toString() {
        return "QuoteRequestResult{id=" + id + ", price='" + price + "', delay=" + delay + "}";
    }
}
