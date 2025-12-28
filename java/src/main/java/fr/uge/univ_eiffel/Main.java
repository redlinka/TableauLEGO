package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.payment_methods.PaymentMethod;
import fr.uge.univ_eiffel.payment_methods.pow.PoWMethod;

public class Main {
    public static void main(String[] args) throws Exception {
        FactoryClient client = FactoryClient.makeFromProps("config.properties");
        PaymentMethod method = new PoWMethod(client);

        System.out.println("Refilling account...");
        method.pay(10);
        System.out.println("Balance is now: " + client.balance());

    }
}
