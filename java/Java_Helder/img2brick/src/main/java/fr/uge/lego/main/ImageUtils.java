package fr.uge.lego.main;

import java.awt.FlowLayout;
import java.awt.image.BufferedImage;
import java.io.File;
import java.io.IOException;

import javax.imageio.ImageIO;
import javax.swing.ImageIcon;
import javax.swing.JFrame;
import javax.swing.JLabel;

public class ImageUtils {
	
	public static void setImageToFrame(BufferedImage image, String title) {
    	ImageIcon imageIcon = new ImageIcon(image);
    	JFrame jFrame = new JFrame();
    	jFrame.setLayout(new FlowLayout());
    	jFrame.setSize(700,500);
    	JLabel jLabel = new JLabel();
    	jFrame.setTitle(title);
    	jLabel.setIcon(imageIcon);
    	jFrame.add(jLabel);    	
    	jFrame.setVisible(true);
    	jFrame.setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
    }
    
    public static boolean saveImageToFile(BufferedImage image, File outputFile) {
        try {
        	boolean success = ImageIO.write(image, "png", outputFile);
        	if (!success) {
        	    System.err.println("Échec de l'écriture du fichier : " + outputFile.getAbsolutePath());
        	}
        	return true;

        } catch (IOException e) {
            e.printStackTrace();
            return false;
        }
    }

}
