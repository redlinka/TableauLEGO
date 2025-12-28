package fr.uge.univ_eiffel.payment_methods;

import java.io.IOException;

public interface PaymentMethod {
    void pay(double amount) throws IOException;
}
