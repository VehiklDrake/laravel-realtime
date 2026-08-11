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
    client_id?: string;
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

export type CorrectGuessResult = {
    player_id: number;
    player: string;
    seconds: number;
    guess_count: number;
};

export type RoundResult = {
    reason: 'correct' | 'timeout';
    intermission_ends_at: string;
    correct_guesses: CorrectGuessResult[];
};

export type Game = {
    id: number;
    code: string;
    status: GameStatus;
    current_round: number;
    total_rounds: number;
    round_duration: number;
    round_intermission_duration: number;
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
    result: RoundResult | null;
    word: string | null;
    masked_word: string | null;
    strokes: Stroke[];
    messages: Message[];
};

export type GameState = {
    server_time: string;
    game: Game;
    player: Player;
    round: Round | null;
};
