# Cahier de Tests Exhaustif — TableauLEGO Games Platform

**Date** : 2026-04-01
**Version** : 1.0
**Outil** : Vitest 3.2.4 + MongoDB Memory Server
**Resultat global** : **141 tests / 141 pass** (28 fichiers)

---

## Table des matieres

1. [Vue d'ensemble](#1-vue-densemble)
2. [Tests unitaires — Board Engine](#2-tests-unitaires--board-engine)
3. [Tests unitaires — Sequence deterministe](#3-tests-unitaires--sequence-deterministe)
4. [Tests unitaires — Scoring Image Rebuild](#4-tests-unitaires--scoring-image-rebuild)
5. [Tests unitaires — Scoring Line Breaker](#5-tests-unitaires--scoring-line-breaker)
6. [Tests unitaires — Regles Line Breaker](#6-tests-unitaires--regles-line-breaker)
7. [Tests unitaires — Systeme de fidelite (Loyalty)](#7-tests-unitaires--systeme-de-fidelite-loyalty)
8. [Tests unitaires — Utilitaires de session solo](#8-tests-unitaires--utilitaires-de-session-solo)
9. [Tests d'integration — Solo Image Rebuild](#9-tests-dintegration--solo-image-rebuild)
10. [Tests d'integration — Solo Line Breaker](#10-tests-dintegration--solo-line-breaker)
11. [Tests d'integration — Mode Duplicate (Rooms)](#11-tests-dintegration--mode-duplicate-rooms)
12. [Tests d'integration — Duplicate Image Rebuild](#12-tests-dintegration--duplicate-image-rebuild)
13. [Tests d'integration — Duplicate Line Breaker](#13-tests-dintegration--duplicate-line-breaker)
14. [Tests d'integration — Chat Duplicate](#14-tests-dintegration--chat-duplicate)
15. [Tests d'integration — Reconnexion Duplicate](#15-tests-dintegration--reconnexion-duplicate)
16. [Tests d'integration — Historique et Fidelite](#16-tests-dintegration--historique-et-fidelite)
17. [Tests d'integration — Fidelite Duplicate](#17-tests-dintegration--fidelite-duplicate)
18. [Tests d'integration — Authentification PHP Bridge](#18-tests-dintegration--authentification-php-bridge)
19. [Tests d'integration — Health, Bootstrap, Puzzles, Auth Guest](#19-tests-dintegration--health-bootstrap-puzzles-auth-guest)
20. [Matrice de couverture](#20-matrice-de-couverture)

---

## 1. Vue d'ensemble

| Categorie                     | Fichiers | Tests | Statut |
|-------------------------------|----------|-------|--------|
| Unitaires — Board Engine      | 2        | 28    | PASS   |
| Unitaires — Sequence          | 2        | 8     | PASS   |
| Unitaires — Score Image       | 2        | 9     | PASS   |
| Unitaires — Score Line        | 2        | 8     | PASS   |
| Unitaires — Regles Line       | 2        | 14    | PASS   |
| Unitaires — Loyalty           | 2        | 23    | PASS   |
| Unitaires — Session Support   | 1        | 16    | PASS   |
| Integration — Solo Image      | 2        | 5     | PASS   |
| Integration — Solo Line       | 2        | 5     | PASS   |
| Integration — Duplicate Room  | 2        | 4     | PASS   |
| Integration — Dup. Image      | 1        | 1     | PASS   |
| Integration — Dup. Line       | 1        | 1     | PASS   |
| Integration — Dup. Chat       | 1        | 2     | PASS   |
| Integration — Dup. Reconnect  | 1        | 2     | PASS   |
| Integration — History+Loyalty | 1        | 1     | PASS   |
| Integration — Dup. Loyalty    | 1        | 1     | PASS   |
| Integration — PHP Auth        | 2        | 6     | PASS   |
| Integration — Health/Bootstrap| 1        | 7     | PASS   |
| **TOTAL**                     | **28**   | **141** | **PASS** |

---

## 2. Tests unitaires — Board Engine

**Fichiers** : `boardEngine.test.ts`, `boardEngine.extended.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | BE-001 | Deduplication des rotations identiques (brick_2x2 = 1 rotation) | `rotations.length === 1` | PASS |
| 2 | BE-002 | Deduplication des rotations (l_corner_3 = 4 rotations) | `rotations.length === 4` | PASS |
| 3 | BE-003 | Deduplication des rotations (frame_3x3 = 1 rotation) | `rotations.length === 1` | PASS |
| 4 | BE-004 | Calcul des cellules absolues depuis rotation et ancre | Coordonnees correctement decalees | PASS |
| 5 | BE-005 | Rejet placement hors limites (debordement droite) | `valid=false, reason=out_of_bounds` | PASS |
| 6 | BE-006 | Rejet placement par collision avec cellule existante | `valid=false, reason=collision` | PASS |
| 7 | BE-007 | Enumeration de tous les placements valides | 12 placements pour brick_1x2 sur grille 3x3 | PASS |
| 8 | BE-008 | Creation d'un board 10x10 par defaut | `width=10, height=10, occupiedCount=0` | PASS |
| 9 | BE-009 | Creation d'un board de taille personnalisee | `width=5, height=3` | PASS |
| 10 | BE-010 | Creation d'un board minimal 1x1 | `width=1, height=1` | PASS |
| 11 | BE-011 | Roundtrip hydrate -> serialize conserve les donnees | Couleurs et dimensions preservees | PASS |
| 12 | BE-012 | Roundtrip preserve les metadonnees lastPlacement | `shapeId, color, offerId` intacts | PASS |
| 13 | BE-013 | Rejet rotation inconnue (45 degres) | `valid=false, reason=unknown_rotation` | PASS |
| 14 | BE-014 | Rejet coordonnee X negative | `valid=false, reason=out_of_bounds` | PASS |
| 15 | BE-015 | Rejet coordonnee Y negative | `valid=false, reason=out_of_bounds` | PASS |
| 16 | BE-016 | Rejet debordement bord droit | `valid=false, reason=out_of_bounds` | PASS |
| 17 | BE-017 | Rejet debordement bord bas | `valid=false, reason=out_of_bounds` | PASS |
| 18 | BE-018 | Acceptation placement valide sur board vide | `valid=true, absoluteCells.length=4` | PASS |
| 19 | BE-019 | Retour tableau vide pour rotation inconnue | `cells === []` | PASS |
| 20 | BE-020 | Decalage correct des cellules depuis l'ancre (2x2) | 4 cellules aux bonnes coordonnees | PASS |
| 21 | BE-021 | Exception sur placement invalide (applyPlacement) | Throw "Cannot apply invalid placement" | PASS |
| 22 | BE-022 | Increment occupiedCount = nombre de cellules de la brique | +3 pour l_corner_3 | PASS |
| 23 | BE-023 | Immutabilite du board original apres applyPlacement | Board source inchange | PASS |
| 24 | BE-024 | Metadonnees des cellules (shapeId, offerId, placedAtTurn) | Valeurs correctement assignees | PASS |
| 25 | BE-025 | Mise a jour du snapshot lastPlacement | Toutes les proprietes presentes | PASS |
| 26 | BE-026 | hasAnyValidPlacementForShape sur board vide | `true` pour toute forme | PASS |
| 27 | BE-027 | hasAnyValidPlacementForShape sur board trop petit | `false` pour brick_1x2 sur 1x1 | PASS |
| 28 | BE-028 | hasAnyValidPlacementForCatalog sur board plein | `false` pour tout le catalogue | PASS |

---

## 3. Tests unitaires — Sequence deterministe

**Fichiers** : `deterministicSequence.test.ts`, `deterministicSequence.extended.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | DS-001 | Meme seed = meme sequence (reproductibilite) | 8 offres identiques | PASS |
| 2 | DS-002 | Seed different = sequence differente | Premiere offre differente | PASS |
| 3 | DS-003 | OfferIds uniques pour 20 indices consecutifs | Set de 20 elements distincts | PASS |
| 4 | DS-004 | Acces non-sequentiel (index 5, 2, 5) | Meme resultat pour meme index | PASS |
| 5 | DS-005 | Distribution des poids respectee (80/20 sur 200 offres) | Ratio rouge entre 0.5 et 0.95 | PASS |
| 6 | DS-006 | Formes toujours issues du catalogue fourni | Tous les shapeId valides | PASS |
| 7 | DS-007 | Couleurs toujours issues de la liste fournie | Toutes les couleurs valides | PASS |
| 8 | DS-008 | issuedAtTurn = sequenceIndex + 1 | Numerotation correcte | PASS |

---

## 4. Tests unitaires — Scoring Image Rebuild

**Fichiers** : `imageRebuildScore.test.ts`, `imageRebuildScore.extended.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | IRS-001 | Metriques et score deterministes (cas de reference) | Score=98, matched=2, accuracy=0.6667 | PASS |
| 2 | IRS-002 | Board completement vide | occupied=0, matched=0, accuracy=0 | PASS |
| 3 | IRS-003 | Correspondance parfaite (100%) | accuracy=1, coverage=1, matched=total | PASS |
| 4 | IRS-004 | Score parfait sans penalites | penalties=0, placementPoints=8, objectivePoints=160 | PASS |
| 5 | IRS-005 | Toutes les cellules sont des erreurs (0% accuracy) | matched=0, mismatched=total | PASS |
| 6 | IRS-006 | Plancher du score a zero (penalites > points) | `score === 0` | PASS |
| 7 | IRS-007 | Bonus de temps integre au score final | Difference = montant du bonus | PASS |
| 8 | IRS-008 | Penalites invalides et tours expires separes | `penalties = 5*3 + 3*4 = 27` | PASS |
| 9 | IRS-009 | Version de formule dans les metadonnees | `image_rebuild_v1` | PASS |

---

## 5. Tests unitaires — Scoring Line Breaker

**Fichiers** : `lineBreakerScore.test.ts`, `lineBreakerScore.extended.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | LBS-001 | Score cumulatif deterministe (cas de reference) | Score=368, lignes=3 | PASS |
| 2 | LBS-002 | Score zero quand rien n'est place | Tous les breakdowns a 0 | PASS |
| 3 | LBS-003 | Plancher du score a zero (penalites elevees) | `score === 0` | PASS |
| 4 | LBS-004 | Points de placement = totalPlacedCells x 2 | `placementPoints === 30` | PASS |
| 5 | LBS-005 | Points de lignes = totalLinesCleared x 50 | `lineClearPoints === 250` | PASS |
| 6 | LBS-006 | Combo + multi-line bonus dans le total | Calcul correct | PASS |
| 7 | LBS-007 | Version de formule dans les metadonnees | `line_breaker_v1` | PASS |
| 8 | LBS-008 | Tous les champs de stats dans les metadonnees | 7 champs verifies | PASS |

---

## 6. Tests unitaires — Regles Line Breaker

**Fichiers** : `lineBreakerRules.test.ts`, `lineBreakerRules.extended.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | LBR-001 | Detection d'une ligne horizontale monochrome complete | 1 ligne row detectee | PASS |
| 2 | LBR-002 | Detection d'une ligne verticale monochrome complete | 1 ligne column detectee | PASS |
| 3 | LBR-003 | Effacement de lignes croisees sans double suppression | 5 cellules effacees, 0 restantes | PASS |
| 4 | LBR-004 | Conservation des cellules non concernees apres effacement | Cellules hors ligne preservees | PASS |
| 5 | LBR-005 | Aucune ligne detectee quand rien n'est complet | `lines === []` | PASS |
| 6 | LBR-006 | Aucune ligne sur un board completement vide | `lines === []` | PASS |
| 7 | LBR-007 | Ligne avec couleurs melangees ignoree | `lines === []` | PASS |
| 8 | LBR-008 | Ligne avec trou (null) ignoree | `lines === []` | PASS |
| 9 | LBR-009 | Detection de 2 lignes horizontales simultanees | 2 lignes detectees | PASS |
| 10 | LBR-010 | Detection simultanee horizontale + verticale | 2 lignes (row + column) | PASS |
| 11 | LBR-011 | Board 1x1 avec une couleur = 2 lignes (row+column) | `lines.length === 2` | PASS |
| 12 | LBR-012 | Effacement retourne un clone quand aucune ligne | clearedCellCount=0, board clone | PASS |
| 13 | LBR-013 | occupiedCount correct apres effacement | Decremente du nombre de cellules effacees | PASS |
| 14 | LBR-014 | Immutabilite du board original apres clearCompletedLines | Board source inchange | PASS |

---

## 7. Tests unitaires — Systeme de fidelite (Loyalty)

**Fichiers** : `loyaltyRewards.test.ts`, `loyaltyRewards.extended.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | LOY-001 | Solo minimum floor avec score 0 | points=10, minimum=6 | PASS |
| 2 | LOY-002 | Classement duplicate win > draw > loss > forfeit | Points decroissants | PASS |
| 3 | LOY-003 | Performance line_breaker : forte > faible | Plus de points pour haute perf | PASS |
| 4 | LOY-004 | Penalite d'abandon (-4 points) | adjustmentPoints=-4 | PASS |
| 5 | LOY-005 | Minimum solo 6 force meme avec abandon + score 0 | `points === 6` | PASS |
| 6 | LOY-006 | Haute performance image_rebuild solo | points > 30 | PASS |
| 7 | LOY-007 | Cap performance solo a 30 | `performancePoints === 30` | PASS |
| 8 | LOY-008 | Cap game bonus solo image_rebuild a 20 | `gameBonusPoints <= 20` | PASS |
| 9 | LOY-009 | Game bonus line_breaker = lines*2 + combo/2 | Calcul correct | PASS |
| 10 | LOY-010 | Game bonus = 0 sans lineMetrics | `gameBonusPoints === 0` | PASS |
| 11 | LOY-011 | Participation duplicate = 12 | `participationPoints === 12` | PASS |
| 12 | LOY-012 | Outcome win = 18 points | `outcomePoints === 18` | PASS |
| 13 | LOY-013 | Outcome draw = 12 points | `outcomePoints === 12` | PASS |
| 14 | LOY-014 | Outcome loss = 8 points | `outcomePoints === 8` | PASS |
| 15 | LOY-015 | Outcome forfeit = 2 points | `outcomePoints === 2` | PASS |
| 16 | LOY-016 | Minimum duplicate forfeit = 2 | `minimumApplied === 2` | PASS |
| 17 | LOY-017 | Minimum duplicate non-forfeit = 8 | `minimumApplied === 8` | PASS |
| 18 | LOY-018 | Cap performance duplicate a 20 | `performancePoints === 20` | PASS |
| 19 | LOY-019 | Game bonus duplicate image_rebuild (accuracyRatio) | Calcul correct | PASS |
| 20 | LOY-020 | Cap game bonus duplicate image_rebuild a 10 | `gameBonusPoints <= 10` | PASS |
| 21 | LOY-021 | Cap game bonus duplicate line_breaker a 10 | `gameBonusPoints <= 10` | PASS |
| 22 | LOY-022 | EntryType = SessionReward pour solo | Correct | PASS |
| 23 | LOY-023 | EntryType = DuplicateBonus pour duplicate | Correct | PASS |

---

## 8. Tests unitaires — Utilitaires de session solo

**Fichier** : `soloSessionSupport.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | SSS-001 | resolveSessionSeed retourne le seed fourni | Seed identique | PASS |
| 2 | SSS-002 | resolveSessionSeed trim le whitespace | Seed trimme | PASS |
| 3 | SSS-003 | resolveSessionSeed genere un UUID si undefined | Format UUID valide | PASS |
| 4 | SSS-004 | resolveSessionSeed genere un UUID si chaine vide | Format UUID valide | PASS |
| 5 | SSS-005 | resolveSessionSeed genere un UUID si whitespace only | Format UUID valide | PASS |
| 6 | SSS-006 | createTimedTurnState : index et deadline corrects | index=1, deadline=start+limit | PASS |
| 7 | SSS-007 | createTimedTurnState : report des skippedOffers/expiredTurns | Valeurs reprises | PASS |
| 8 | SSS-008 | computeRemainingTurnMs : temps restant positif | > 14000 et <= 15000 | PASS |
| 9 | SSS-009 | computeRemainingTurnMs : zero si deadline passee | `remaining === 0` | PASS |
| 10 | SSS-010 | computeRemainingTurnMs : zero si deadlineAt undefined | `remaining === 0` | PASS |
| 11 | SSS-011 | calculateTurnTimeBonus : bonus max quand temps plein | `bonus === 3` | PASS |
| 12 | SSS-012 | calculateTurnTimeBonus : zero si deadline passee | `bonus === 0` | PASS |
| 13 | SSS-013 | calculateTurnTimeBonus : zero si deadlineAt undefined | `bonus === 0` | PASS |
| 14 | SSS-014 | calculateTurnTimeBonus : zero si turnTimeLimitMs=0 | `bonus === 0` | PASS |
| 15 | SSS-015 | calculateTurnTimeBonus : zero si maxBonus=0 | `bonus === 0` | PASS |
| 16 | SSS-016 | calculateTurnTimeBonus : proportionnel a mi-temps | `bonus === 1` (50% de 2) | PASS |

---

## 9. Tests d'integration — Solo Image Rebuild

**Fichiers** : `soloImageRebuild.integration.test.ts`, `soloImageRebuild.extended.integration.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | SIR-001 | Flux complet : creation, jeu, finalisation + historique | Session finished, score numerique, historique present | PASS |
| 2 | SIR-002 | Rejet placement hors limites (99,99) | `accepted === false` | PASS |
| 3 | SIR-003 | Abandon de session | `finished=true, finishReason=abandoned` | PASS |
| 4 | SIR-004 | Session inexistante retourne 404 | `statusCode === 404` | PASS |
| 5 | SIR-005 | Requete sans token retourne 401 | `statusCode === 401` | PASS |

---

## 10. Tests d'integration — Solo Line Breaker

**Fichiers** : `soloLineBreaker.integration.test.ts`, `soloLineBreaker.extended.integration.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | SLB-001 | Flux complet : creation, jeu, finalisation + historique | Session finished, lineClearStats coherentes | PASS |
| 2 | SLB-002 | Rejet placement hors limites (99,99) | `accepted === false` | PASS |
| 3 | SLB-003 | Abandon de session | `finished=true, finishReason=abandoned` | PASS |
| 4 | SLB-004 | Session inexistante retourne 404 | `statusCode === 404` | PASS |
| 5 | SLB-005 | Requete sans token retourne 401 | `statusCode === 401` | PASS |

---

## 11. Tests d'integration — Mode Duplicate (Rooms)

**Fichiers** : `duplicateRoom.integration.test.ts`, `duplicateRoom.extended.integration.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | DR-001 | Flux complet : create, join, ready, start | Phase = in_game, meme offerId pour les 2 joueurs | PASS |
| 2 | DR-002 | Rejet join avec code de room invalide | Message d'erreur avec code | PASS |
| 3 | DR-003 | Guest ne peut pas demarrer la room (host-only) | Message d'erreur avec code | PASS |
| 4 | DR-004 | Impossible de demarrer si tous les joueurs ne sont pas prets | Message d'erreur avec code | PASS |

---

## 12. Tests d'integration — Duplicate Image Rebuild

**Fichier** : `duplicateImageRebuild.integration.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | DIR-001 | Match complet avec sequence partagee et resultat final | Phase=finished, 2 scores >= 0 | PASS |

---

## 13. Tests d'integration — Duplicate Line Breaker

**Fichier** : `duplicateLineBreaker.integration.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | DLB-001 | Match complet avec scores independants + rejet placement invalide | Phase=finished, scores differents | PASS |

---

## 14. Tests d'integration — Chat Duplicate

**Fichier** : `duplicateChat.integration.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | DC-001 | Broadcast d'un message chat + persistance en base | Message recu par l'autre joueur + stocke | PASS |
| 2 | DC-002 | Rejet d'un message depassant la longueur max | `error.code === CHAT_TOO_LONG` | PASS |

---

## 15. Tests d'integration — Reconnexion Duplicate

**Fichier** : `duplicateReconnect.integration.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | DRC-001 | Resume dans la fenetre de grace | Session resumed, host notifie reconnexion | PASS |
| 2 | DRC-002 | Forfait apres expiration de la grace window | Phase=finished, outcome=forfeit | PASS |

---

## 16. Tests d'integration — Historique et Fidelite

**Fichier** : `historyLoyalty.integration.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | HL-001 | Persistence des points + detail enrichi d'historique | History, detail, balance, ledger coherents | PASS |

---

## 17. Tests d'integration — Fidelite Duplicate

**Fichier** : `duplicateLoyalty.integration.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | DL-001 | History + loyalty ledger pour les 2 joueurs duplicate | Les 2 joueurs ont historique + points > 0 | PASS |

---

## 18. Tests d'integration — Authentification PHP Bridge

**Fichiers** : `phpAuth.integration.test.ts`, `phpAuth.extended.integration.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | PA-001 | Echange token PHP valide -> token Node + pseudonymisation | playerId php_, hash different du sub, loyaltyId technique | PASS |
| 2 | PA-002 | Rejet token PHP expire | `statusCode >= 400` | PASS |
| 3 | PA-003 | Rejet token signe avec le mauvais secret | `statusCode >= 400` | PASS |
| 4 | PA-004 | Rejet token avec mauvais issuer | `statusCode >= 400` | PASS |
| 5 | PA-005 | Rejet token malformed / garbage | `statusCode >= 400` | PASS |
| 6 | PA-006 | Meme subject PHP = meme playerId sur echanges repetes | playerIds identiques | PASS |

---

## 19. Tests d'integration — Health, Bootstrap, Puzzles, Auth Guest

**Fichier** : `healthBootstrap.integration.test.ts`

| # | ID | Cas de test | Resultat attendu | Statut |
|---|-----|-------------|-----------------|--------|
| 1 | HB-001 | Health check retourne 200 + status ok | `{ status: "ok" }` | PASS |
| 2 | HB-002 | Bootstrap sans authentification | 200, games + bricks + puzzles presents | PASS |
| 3 | HB-003 | Bootstrap avec authentification (enrichi) | currentPlayer present, authSource=guest | PASS |
| 4 | HB-004 | Liste des puzzles | 200, au moins 1 puzzle | PASS |
| 5 | HB-005 | Detail d'un puzzle par ID | 200, id + cells presents | PASS |
| 6 | HB-006 | Creation compte invite avec alias | 200, alias preserve, authSource=guest | PASS |
| 7 | HB-007 | Consultation etat session en cours de partie | 200, board.occupiedCount > 0, turn.index >= 2 | PASS |

---

## 20. Matrice de couverture

### Par composant fonctionnel

| Composant | Happy Path | Cas limites | Cas d'erreur | Securite |
|-----------|:----------:|:-----------:|:------------:|:--------:|
| Board Engine | OK | OK | OK | N/A |
| Sequence Deterministe | OK | OK | N/A | N/A |
| Score Image Rebuild | OK | OK | OK | N/A |
| Score Line Breaker | OK | OK | OK | N/A |
| Detection/Clear Lignes | OK | OK | OK | N/A |
| Systeme de Fidelite | OK | OK | OK | N/A |
| Utilitaires Session | OK | OK | OK | N/A |
| Solo Image Rebuild | OK | OK | OK | OK |
| Solo Line Breaker | OK | OK | OK | OK |
| Rooms Duplicate | OK | OK | OK | N/A |
| Duplicate Image Rebuild | OK | -- | -- | N/A |
| Duplicate Line Breaker | OK | OK | -- | N/A |
| Chat Duplicate | OK | OK | OK | N/A |
| Reconnexion Duplicate | OK | OK | OK | N/A |
| Historique + Fidelite | OK | -- | -- | N/A |
| Auth Guest | OK | -- | OK | OK |
| Auth PHP Bridge | OK | OK | OK | OK |
| Health / Bootstrap | OK | -- | -- | N/A |
| Puzzles API | OK | -- | -- | N/A |

### Par type de jeu et mode

| Scenario | Solo | Duplicate |
|----------|:----:|:---------:|
| Image Rebuild — Flux complet | OK | OK |
| Image Rebuild — Placement invalide | OK | -- |
| Image Rebuild — Abandon | OK | N/A |
| Image Rebuild — Session 404 | OK | -- |
| Image Rebuild — Score | OK | OK |
| Line Breaker — Flux complet | OK | OK |
| Line Breaker — Placement invalide | OK | OK |
| Line Breaker — Abandon | OK | N/A |
| Line Breaker — Session 404 | OK | -- |
| Line Breaker — Score | OK | OK |
| Detection lignes | N/A | N/A |
| Fidelite solo | OK | -- |
| Fidelite duplicate | -- | OK |
| Historique post-partie | OK | OK |

### Legende

- **OK** : Couvert par au moins un test
- **--** : Non couvert (risque faible ou couvert indirectement)
- **N/A** : Non applicable

---

## Conclusion

La suite de tests couvre **141 cas** repartis sur **28 fichiers**, validant l'ensemble des mecaniques de jeu (solo et duplicate), les formules de scoring, le systeme de fidelite, la gestion des rooms WebSocket, la reconnexion, le chat, l'authentification (guest + PHP bridge), et les API publiques.

**Base solide confirmee** pour la suite du developpement.
