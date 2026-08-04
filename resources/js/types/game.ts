export type GameStatus = 'waiting' | 'playing' | 'finished';

export type Player = {
    id: number;
    name: string;
    score: number;
    is_host: boolean;
};

export type Stroke = {
    id?: number;
    player_id?: number;
    from_x: number;
    from_y: number;
    to_x: number;
    to_y: number;
    color: string;
    size: number;
};

export type Message = {
    id: number | string;
    round_id: number;
    round_number: number;
    player_id: number;
    player: string;
    message: string;
    is_correct: boolean;
};

export type Game = {
    id: number;
    code: string;
    status: GameStatus;
    current_round: number;
    total_rounds: number;
    round_duration: number;
    players: Player[];
};

export type Round = {
    id: number;
    number: number;
    drawer_id: number;
    drawer_name: string;
    started_at: string;
    ends_at: string;
    ended_at: string | null;
    word: string | null;
    masked_word: string | null;
    strokes: Stroke[];
    messages: Message[];
};

export type GameState = {
    game: Game;
    player: Player;
    round: Round | null;
};
