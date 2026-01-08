package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.mediators.FactoryClient;
import fr.uge.univ_eiffel.payment_methods.PaymentMethod;
import fr.uge.univ_eiffel.payment_methods.PoWMethod;

public class PaymentTester {
    public static void main(String[] args) throws Exception {
        if (args.length != 1) {
            System.err.println("Usage: java PaymentTester <amount>");
            System.exit(1);
        }
        final int amount;
        try {
            amount = Integer.parseInt(args[0]);
        } catch (NumberFormatException e) {
            System.err.println("Invalid amount: " + args[0]);
            System.exit(2);
            return;
        }

        final FactoryClient client = FactoryClient.makeFromProps("config.properties");
        final PaymentMethod method = new PoWMethod(client);

        System.out.println("Refilling account...");
        System.out.println("Current account balance: " + client.balance());
        method.pay(amount);
        System.out.println("Balance is now: " + client.balance());
    }
}
