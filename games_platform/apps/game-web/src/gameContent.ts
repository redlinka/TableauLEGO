import { GameMode, GameType } from "@games-platform/game-contracts";

export const GAME_CONTENT: Record<
  GameType,
  {
    title: string;
    tag: string;
    shortDescription: string;
    objective: string;
    setupLabel: string;
    turnAction: string;
    scoreHint: string;
    howToPlay: string[];
    rules: string[];
  }
> = {
  [GameType.ImageRebuild]: {
    title: "Puzzle de briques",
    tag: "Jeu 1",
    shortDescription:
      "Reconstituez une image en placant des briques colorees une par une.",
    objective:
      "Placez les briques au bon endroit pour reproduire l'image modele.",
    setupLabel: "Choisissez un puzzle, puis lancez la partie.",
    turnAction: "Placez la brique sur votre plateau pour reproduire l'image.",
    scoreHint:
      "Plus vos couleurs correspondent a l'image, plus vous gagnez de points !",
    howToPlay: [
      "Une image modele vous est montree a gauche.",
      "A chaque tour, une brique coloree apparait.",
      "Cliquez sur votre plateau pour la poser au bon endroit."
    ],
    rules: [
      "Une image modele s'affiche : c'est votre objectif",
      "A chaque tour, une brique d'une couleur apparait",
      "Cliquez sur une case de votre plateau pour la placer",
      "Essayez de reproduire les couleurs de l'image modele",
      "Vous avez 30 secondes par tour",
      "La partie se termine quand toutes les briques ont ete jouees"
    ]
  },
  [GameType.LineBreaker]: {
    title: "Line Breaker",
    tag: "Jeu 2",
    shortDescription:
      "Formez des lignes completes d'une seule couleur pour les effacer.",
    objective:
      "Remplissez des lignes entieres avec une meme couleur pour marquer des points.",
    setupLabel: "Lancez une partie et commencez a former des lignes.",
    turnAction: "Placez la brique pour completer une ligne d'une meme couleur.",
    scoreHint:
      "Effacez plusieurs lignes d'un coup ou enchainez les combos pour multiplier vos points !",
    howToPlay: [
      "Votre plateau est vide au depart.",
      "A chaque tour, une brique coloree apparait.",
      "Quand une ligne ou colonne est entierement d'une couleur, elle disparait."
    ],
    rules: [
      "Vous commencez avec un plateau vide de 10x10",
      "A chaque tour, une brique d'une couleur apparait",
      "Cliquez sur une case pour la placer",
      "Si une ligne ou colonne entiere est d'une seule couleur, elle est effacee",
      "Effacer plusieurs lignes d'un coup donne un bonus",
      "Enchainer des effacements consecutifs cree un combo"
    ]
  }
};

export const MODE_CONTENT: Record<
  GameMode,
  {
    title: string;
    shortDescription: string;
    primaryAction: string;
  }
> = {
  [GameMode.Solo]: {
    title: "Solo",
    shortDescription:
      "Jouez seul et essayez d'obtenir le meilleur score.",
    primaryAction: "Jouer en solo"
  },
  [GameMode.Duplicate2P]: {
    title: "En ligne (2 joueurs)",
    shortDescription:
      "Affrontez un autre joueur avec les memes briques. Le meilleur score gagne !",
    primaryAction: "Jouer en ligne"
  }
};

export const POINTS_EXPLANATION = {
  title: "Vos points de fidelite",
  howToEarn: [
    "Chaque partie jouee vous rapporte des points",
    "Plus votre score est eleve, plus vous gagnez de points",
    "Gagner en mode 2 joueurs rapporte un bonus supplementaire"
  ],
  tiers: [
    { points: 200, discount: "5%" },
    { points: 500, discount: "10%" },
    { points: 1000, discount: "20%" }
  ],
  callToAction: "Utilisez vos points sur la boutique pour obtenir des reductions !"
};
