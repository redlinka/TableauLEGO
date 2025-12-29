package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.interfaces.FactoryClient;
import fr.uge.univ_eiffel.payment_methods.PaymentMethod;
import fr.uge.univ_eiffel.payment_methods.pow.PoWMethod;

public class PaymentTester {
    public static void main(String[] args) throws Exception {
        final FactoryClient client = FactoryClient.makeFromProps("config.properties");
        final PaymentMethod method;

        method = new PoWMethod(client);

        System.out.println("Refilling account...");
        method.pay(1);
        System.out.println("Balance is now: " + client.balance());

    }
}
