package fr.univ_eiffel.pixelisation;

import java.awt.image.BufferedImage;

public interface PixelizationMethod {
    void pixelize(BufferedImage source, BufferedImage destination);
}
