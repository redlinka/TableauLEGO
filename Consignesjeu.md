Vous êtes désormais à la tête d’une boutique de briques de Lego connaissant un succès certain… néanmoins il semble qu’il manque un petit rien qui permettrait de fidéliser la clientèle et la faire revenir régulièrement sur votre site. Après réflexion, vous décidez que vous allez mettre en place un programme de fidélité s’insérant dans un système de gamification.



Le principe consiste à proposer des jeux en ligne à vos visiteurs (sur le thème des briques Lego bien sûr) qui leur permettront, outre de s’amuser, de gagner des points de fidélité utilisables pour des achats sur votre boutique. Afin de rendre votre clientèle plus captive, vous allez proposer, outre un site de jeu réalisé avec React, une application mobile qui permettra de communiquer avec vos clients par l’intermédiaire de notifications. Vous pourrez outre leur proposer des offres exclusives, les inciter à venir jouer en ligne en solo ou avec leurs amis.



Vous devez imaginer que le travail demandé a été confié à un sous-traitant externe (en pratique c’est vous qui allez également jouer le rôle de ce sous-traitant). Le sous-traitant n’est pas censé gérer le site web PHP de votre boutique : il ne s’occupe que du backend de jeu de fidélisation Node.js, de le base de données MongoDB qui lui est associé ainsi que du frontend web React fonctionnant avec le backend Node.js. Il ne doit connaître aucune information personnelle de votre base clients (nom, adresse email, téléphone, adresse postale, …). Votre site PHP générera un identifiant de fidélité pour chaque client et le backend Node.js et son frontend React n’auront connaissance que de celui-ici pour identifier un client. Ainsi toute fuite de données chez le sous-traitant ne pourra révéler que cet identifiant et les sessions de jeu associées (avec leurs point de fidélité).



Application de jeu en React

Nous vous proposons deux jeux à réaliser sur le thème des briques Lego. Ces jeux consisteront à réaliser un tableau de briques rectangulaire répondant à certaines contraintes. Ils pourront être joués avec un seul joueur ou deux joueurs.



Pour ces deux jeux, nous démarrons d’un tableau vide (matrice rectangulaire d’une taille fixée). A chaque tour de jeu, une brique est tirée au sort : le joueur doit placer cette brique à un emplacement libre du tableau. En mode duplicate (2 joueurs), chaque participant travaille sur son propre tableau mais les briques proposées à chaque tour sont les mêmes pour les deux joueurs. Chaque joueur a la liberté de placer la brique à l’emplacement de son choix. Un tour de jeu est limité en temps : si un joueur n’a pas choisi l’emplacement de la brique avant l’échéance du temps limite, celle-ci est positionnée à un emplacement aléatoire de son tableau (qui n’est pas nécessairement optimal).



En mode duplicate, chacun des joueurs doit pouvoir voir son tableau ainsi que celui de son adversaire.



Vos jeux devront être ergonomiques et offrir une expérience agréable aussi bien sur un ordinateur de bureau qu’un téléphone ou une tablette tactile. L’usage du clavier devra être possible sur un ordinateur de bureau. Vous devrez également adapter la taille du tableau de jeu à la taille de l’écran du joueur.



Nous décrivons maintenant plus en détails les deux modes de jeu que vous devez implémenter.



1er jeu : reproduction d’une image

Ce mode de jeu consiste à reproduire une image qui est affichée. Vous aurez préalablement pixellisé cette image et déterminé un pavage avec un jeu de briques pour la réaliser en utilisant les composants logiciels réalisés au semestre dernier. Chaque brique (caractérisée par sa forme et sa couleur) du jeu de brique réalisé est ensuite distribuée (dans un ordre aléatoire) pour chaque tour de jeu. Le joueur doit alors la positionner à l’emplacement qui lui semble le plus adéquat afin de reproduire le plus fidèlement l’image.



Un score est déterminé pour chaque joueur qui permettra de quantifier la fidélité de reproduction de l’image. En mode bi-joueurs, le gagnant de la partie est celui qui aura le meilleur score et donc la meilleur fidélité de reproduction de l’image.



2ème jeu : casse-briques de lignes

Le deuxième mode s’inspire du célèbre jeu Tetris. Il ne s’agit pas de reproduire une image mais de placer chacune des briques de façon à constituer des lignes de même couleur sur le tableau. Lorsqu’une ligne de même couleur est constituée (qui peut être horizontale ou verticale à n’importe quel emplacement du tableau), celle-ci est supprimée. Cela peut impliquer de briser des briques.



Pour ce jeu vous pourrez proposer d’utiliser un jeu plus restreint de briques et de couleurs que ce qui est proposé dans le référentiel complet. Il est notamment requis d’utiliser des briques lacunaires (qui ne soient pas de forme rectangulaire ou carrée).



A noter qu’il est autorisé (comme dans le jeu Tetris) de réaliser une rotation des briques avant de les placer. En revanche, contrairement à Tetris, vous avez le droit de positionner la brique proposée à chaque tour de jeu à n’importe quel emplacement (pas de notion de gravité et de chute).



Pour chaque ligne constituée (et supprimée), le score du joueur est augmenté. Vous pourrez bonifier le gain de score lorsque plusieurs lignes sont constituées simultanément. Vous pouvez aussi choisir une politique de score avec des couleurs plus valorisées que d’autres.



Le jeu s’arrête lorsque le joueur abandonne ou lorsqu’il n’est plus possible de positionner la brique du tour courant (le tableau est plein).



A propos du mode duplicate

Le mode duplicate nécessite de mettre en œuvre un système de salon d’attente afin que les deux joueurs soient connectés et que la partie débute. Le 1er joueur est considéré comme l’administrateur de la partie : il créé une nouvelle partie et invite le 2nd joueur à la rejoindre en lui communiquant un code transmis par le serveur. Lorsque le 2nd joueur le rejoint en se rendant sur le site et en communiquant le code (qui peut être transmis par une URL), les deux joueurs ont la possibilité de discuter par messages textuels. Lorsque le 1er joueur le décide, la partie est lancée. Au cours de la partie, les deux joueurs ont la possibilité également de communiquer par messages textuels.



Jeux pour les apprentis

Le groupe des apprentis aura le privilège de n’avoir qu’un seul des deux jeux à implémenter (cela pourra être celui de leur choix).



Architecture du code

Il sera tenu compte des efforts réalisés afin d’architecturer le code de votre application et en particulier éviter les redondances. Votre application devra être maintenable et évolutive. Ainsi par exemple la mise en œuvre d’un nouveau jeu devrait être facilitée et tous les éléments communs aux deux jeux demandés doivent reposer sur le même code.



Points de fidélité

Les jeux doivent être accessibles depuis votre boutique aussi bien pour des utilisateurs authentifiés que pour les visiteurs n’ayant pas encore de compte. Pour chaque partie réalisée, des points de fidélité doivent être offerts au joueur, et ce quel que soit son score (qu’il ait gagné ou perdu si l’on est en mode duplicate). Naturellement on sera plus généreux si le joueur a réalisé un score honorable. C’est à vous de définir la politique d’attribution de points de fidélité en fonction de l’issue des jeux. Cette politique pourra être décrite dans un fichier JSON qui sera proposé par une API du site PHP et qui pourra être récupérée par le backend Node.js. Cette politique pourra être dynamique dans le temps : il peut être possible par exemple de proposer une gratification en points plus avantageuse à certaines heures ou certains jours de la semaine.



Les points de fidélité attribués ont une date d’expiration qui peut être variable (points valable une journée, une semaine, un mois, un an…) ; c’est à vous de déterminer la validité des points dans votre politique d’attribution de points.



Le frontend React devra proposer une interface permettant de visualiser l’historique des parties jouées avec les scores obtenus au cours de celles-ci et les points correspondants gagnés. Ces informations devront être conservées dans la base MongoDB et associés à l’identifiant de fidélité (aucune autre information personnelle ne doit être conservée dans cette base).



Le site PHP de la boutique devra être capable de connaître les points de fidélité de chaque client en interrogeant une API exposée sur le backend Node.js. Lors du processus de passage de commande, le solde de points disponible devra être affiché : le client aura la possibilité de choisir le nombre de points qu’il souhaite utiliser pour obtenir un bon d’achat applicable sur sa commande. Vous conviendrez de la valeur monétaire de chaque point. Lorsque la commande est validée, le site PHP devra appeler une API du backend NodeJS afin de consommer les points utilisés ; les points d’échéance la plus proche devront être consommés en priorité.



Dans le cas d’un visiteur non authentifié, le backend Node.js génerera un nouvel identifiant de fidélité. A la fin de sa session de jeu, il sera invité à se rendre sur le site PHP de la boutique afin d’y créer un compte : on pourra alors rattacher à son compte créé l’identifiant de fidélité.



Choix techniques pour la réalisation de l’application de jeu

Pour la réalisation de l’application de jeu, vous devrez utiliser la bibliothèque React avec le langage TypeScript. Vous devrez communiquer avec le serveur web de jeu (réalisé avec Node.js) en utilisant le protocole WebSocket. Les données seront stockées dans une base MongoDB.



Application mobile de fidélisation

L’application mobile va permettre de fidéliser notre clientèle et la rendre plus captive en lui envoyant des notifications pour l’inciter à venir commander de nouveaux tableaux Lego et à venir jouer.



Installation de l’application

Votre boutique doit comporter un lien permettant de télécharger le fichier APK permettant d’installer votre application sur un système Android. Lors du premier lancement de l’application, celle-ci contacte votre site PHP afin de l’informer de l’installation. Cela vous permet d’avoir des statistiques d’installation de l’application visualisables pour l’admnistrateur de votre site PHP.



A intervalle de temps régulier (par exemple chaque jour), l’application doit contacter le serveur PHP afin de lui signaler sa présence : cela permet d’avoir des statistiques concernant les applications actives.



Achat et jeu depuis l’application

L’application vous permet d’acheter sur la boutique et jouer par l’intermédiaire d’un composant WebView intégrant le site de la boutique et le site de jeu. Lorsque le client se connecte sur son compte sur le site PHP affiché par la WebView, l’application Android doit être informée de l’identité de l’utilisateur et doit également recevoir un jeton d’authentification qu’elle pourra conserver dans ses préférences. Ainsi l’application a connaissance de l’identité de l’utilisateur et peut utiliser le jeton pour s’authentifier de façon transparente sur le site web de la boutique : l’utilisateur n’a plus besoin de s’authentifier pour les usages ultérieurs de l’application (sauf s’il souhaite se déconnecter dans l’application).



Notifications pour le processus d’achat

Lorsque le client réalise une commande sur le site (que ce soit sur le site en dehors de l’application ou dans l’application), l’application doit afficher une notification pour lui confirmer sa commande. On pourra aussi émettre des notifications pour signaler la validation du paiement voire même la possible expédition de la commande.



Notifications d’image du jour

Le site propose pour chaque jour une image prédéfinie pour laquelle le client peut commander un tableau de briques à prix préférentiel. Le client reçoit une notification par l’intermédiaire de l’application chaque jour pour lui présenter cette image. Lorsque l’utilisateur clique sur cette notification, l’application affiche une page l’invitant à commander ce tableau de briques.



La fonctionnalité de notification pour l’image du jour est désactivable depuis l’application.



Notifications de fidélisation

Si nous constatons qu’un client disposant de l’application n’a pas réalisé de commande depuis un certain temps (ou n’a pas joué), nous pouvons lui envoyer une notification pour l’inviter à commander ou à jouer. Pour mettre en œuvre cette fonctionnalité, l’application contacte régulièrement le serveur PHP afin de lui demander si une notification de fidélisation doit être affichée. Cela peut être fait par exemple à l’occasion du “ping” régulier sur le serveur PHP : l’application signale sa présence au serveur, et le serveur lui indique si une notification doit être affichée.



Documentation

Pour chacune des deux parties (application React et application Android), vous devrez rédigez une documentation au format Markdown. Cette documentation (comme le rendu du projet global) est commune à l’équipe.



Il sera demandé également une documentation individuelle (également au format Markdown) pour chaque personne membre de l’équipe où celle-ci devra décrire précisément les apports personnels apportés au projet. Cette documentation individuelle pourra être commune à la partie React et Android. La démarche employée devra être expliquée avec les difficultés rencontrées et les solutions apportées. Il sera possible également de formuler des remarques personnelles quant à la SAE : le sujet a-t-il été apprécié ? des modifications pourraient-elle réalisées pour l’organisation de la SAE ? Une petite vidéo individuelle d’une durée de 3 minutes (+- 15 s) consistant en une démonstration du projet devra être jointe à la documentation Markdown : cette vidéo consistera à faire une démonstration du projet en soulignant les apports personnels.

