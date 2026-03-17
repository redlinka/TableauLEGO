import { ChatMessageRepository } from "./ChatMessageRepository.js";
import { GameEventRepository } from "./GameEventRepository.js";
import { GameSessionRepository } from "./GameSessionRepository.js";
import { HistoryRepository } from "./HistoryRepository.js";
import { LoyaltyLedgerRepository } from "./LoyaltyLedgerRepository.js";
import { PlayerRepository } from "./PlayerRepository.js";

export interface RepositoryBundle {
  players: PlayerRepository;
  gameSessions: GameSessionRepository;
  gameEvents: GameEventRepository;
  chatMessages: ChatMessageRepository;
  history: HistoryRepository;
  loyaltyLedger: LoyaltyLedgerRepository;
}

export function createRepositories(): RepositoryBundle {
  return {
    players: new PlayerRepository(),
    gameSessions: new GameSessionRepository(),
    gameEvents: new GameEventRepository(),
    chatMessages: new ChatMessageRepository(),
    history: new HistoryRepository(),
    loyaltyLedger: new LoyaltyLedgerRepository()
  };
}
