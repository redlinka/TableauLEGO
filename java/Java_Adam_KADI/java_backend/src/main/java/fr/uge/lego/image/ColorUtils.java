package fr.uge.lego.image;

/**
 * Helper for interpolation and colour computations.
 */
public final class ColorUtils {

    private ColorUtils() {}

    public static double bilinear(double c00, double c10, double c01, double c11,
                                  double dx, double dy) {
        double c0 = c00 * (1 - dx) + c10 * dx;
        double c1 = c01 * (1 - dx) + c11 * dx;
        return c0 * (1 - dy) + c1 * dy;
    }

    /**
     * Catmull-Rom cubic weight for bicubic interpolation.
     */
    public static double cubicWeight(double x) {
        x = Math.abs(x);
        if (x <= 1) {
            return 1 - 2 * x * x + x * x * x;
        }
        if (x < 2) {
            return 4 - 8 * x + 5 * x * x - x * x * x;
        }
        return 0;
    }
}
