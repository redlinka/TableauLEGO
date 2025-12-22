package fr.uge.lego.factory.dto;

/**
 * Response from /billing/balance.
 */
public final class BillingBalance {
    public String amount;

    @Override
    public String toString() {
        return "BillingBalance{amount='" + amount + "'}";
    }
}
