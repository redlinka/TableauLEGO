package fr.uge.lego.brick;

import java.util.Arrays;
import java.util.Objects;

/**
 * Represents a brick produced by the factory, including its certificate.
 */
public final class Brick {
    private final BrickType type;
    private final BrickColor color;
    private final byte[] serial;
    private final byte[] certificate;

    public Brick(BrickType type, BrickColor color, byte[] serial, byte[] certificate) {
        this.type = Objects.requireNonNull(type);
        this.color = Objects.requireNonNull(color);
        this.serial = serial.clone();
        this.certificate = certificate.clone();
    }

    public BrickType type() { return type; }
    public BrickColor color() { return color; }
    public byte[] serial() { return serial.clone(); }
    public byte[] certificate() { return certificate.clone(); }

    @Override
    public String toString() {
        return "Brick{" + type + ", " + color + ", serial=" + toHex(serial) + "}";
    }

    private static String toHex(byte[] data) {
        StringBuilder sb = new StringBuilder(data.length * 2);
        for (byte b : data) {
            sb.append(String.format("%02X", b));
        }
        return sb.toString();
    }
}
