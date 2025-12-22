package fr.univ_eiffel.pixelisation;

public class PixelizationFactory {

    public static PixelizationMethod get(String name) {
        return switch (name.toLowerCase()) {
            case "basic" -> new BasicResolutionChanger();
            case "bilinear" -> new BilinearPixelizer();
            default -> throw new IllegalArgumentException("Unknown pixelization method: " + name);
        };
    }
}