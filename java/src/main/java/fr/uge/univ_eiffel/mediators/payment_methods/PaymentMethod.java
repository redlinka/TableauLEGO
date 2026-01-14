package fr.uge.univ_eiffel.mediators.payment_methods;

import java.io.IOException;

public sealed interface PaymentMethod permits PoWMethod {
    void pay(double amount) throws IOException;
}
