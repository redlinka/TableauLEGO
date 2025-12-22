package fr.uge.lego.main;

import javax.imageio.ImageIO;

import fr.uge.lego.image.*;
import fr.uge.lego.matrix.Matrix;

import java.awt.image.BufferedImage;
import java.io.File;
import java.io.IOException;
import java.util.Arrays;

public class Main {
	public static void main(String[] args) {
		try {

			File inputFile = new File(
					"Votre chemin//img2brick//src//main//java//ressources//multiStepResult.jpg");
			BufferedImage original = ImageIO.read(inputFile);

			ResizeStrategy strategy1 = new NearestNeighborResize();
			ResizeStrategy strategy2 = new BilinearResize(); 
			ResizeStrategy strategy3 = new BicubicResize();

			ResizeStrategy multiStrategy = new MultiStepResizeStrategy(Arrays.asList(strategy1, strategy2, strategy3));

			BufferedImage resized = multiStrategy.resize(original, 128, 128);

			File outputFile = new File(
					"Votre chemin//img2brick//src//main//java//ressources//multiStepResult.jpg");
			ImageUtils.saveImageToFile(resized, outputFile);

			ImageUtils.setImageToFrame(resized, "Résultat multi-étapes");

		} catch (IOException e) {
			e.printStackTrace();
		}
	}
}
