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
  }
> = {
  [GameType.ImageRebuild]: {
    title: "Reproduction d'image",
    tag: "Jeu 1",
    shortDescription:
      "Reproduis le modele avec les briques proposees, tour apres tour.",
    objective:
      "Place chaque brique au bon endroit pour obtenir la reproduction la plus fidele possible.",
    setupLabel: "Choisis un puzzle, puis lance une partie.",
    turnAction: "Place la brique courante pour te rapprocher de l'image cible.",
    scoreHint:
      "Tu marques plus de points quand les couleurs et les positions ressemblent au modele.",
    howToPlay: [
      "But : reproduire l'image cible le plus fidelement possible.",
      "A chaque tour : une brique coloree t'est proposee.",
      "Action : pose-la au bon endroit sur ton plateau."
    ]
  },
  [GameType.LineBreaker]: {
    title: "Line Breaker",
    tag: "Jeu 2",
    shortDescription:
      "Assemble des lignes completes d'une seule couleur pour les faire disparaitre.",
    objective:
      "Construis des lignes horizontales ou verticales monochromes pour marquer des points.",
    setupLabel: "Lance une partie et commence a construire tes premieres lignes.",
    turnAction: "Place la brique courante pour completer une ligne d'une meme couleur.",
    scoreHint:
      "Tu gagnes plus de points quand tu effaces plusieurs lignes ou que tu enchaines les combos.",
    howToPlay: [
      "But : former des lignes completes d'une seule couleur.",
      "A chaque tour : une brique apparait avec sa couleur.",
      "Action : pose-la pour declencher des lignes et des combos."
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
    title: "Mode solo",
    shortDescription:
      "Tu joues seul contre le plateau et le serveur valide chaque placement.",
    primaryAction: "Lancer une partie"
  },
  [GameMode.Duplicate2P]: {
    title: "Mode duplicate",
    shortDescription:
      "Deux joueurs recoivent les memes briques. Le meilleur score l'emporte.",
    primaryAction: "Creer ou rejoindre un salon"
  }
};
