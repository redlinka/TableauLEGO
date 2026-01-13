package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.mediators.legofactory.FactoryLoader;
import fr.uge.univ_eiffel.mediators.legofactory.LegoFactory;
import fr.uge.univ_eiffel.mediators.payment_methods.PaymentMethod;
import fr.uge.univ_eiffel.mediators.payment_methods.PoWMethod;

public class Refiller {
    public static void main(String[] args) throws Exception {
        if (args.length != 1) {
            System.err.println("Usage: java PaymentTester <amount>");
        }
        final int amount;
        try {
            amount = Integer.parseInt(args[0]);
        } catch (NumberFormatException e) {
            System.err.println("Invalid amount: " + args[0]);
            System.exit(2);
            return;
        }

        final LegoFactory factory = FactoryLoader.loadFromProps("config.properties");
        final PaymentMethod method = new PoWMethod(factory);

        System.out.println("Refilling account...");
        System.out.println("Current account balance: " + factory.balance());
        method.pay(amount);
        System.out.println("Balance is now: " + factory.balance());
    }
}
