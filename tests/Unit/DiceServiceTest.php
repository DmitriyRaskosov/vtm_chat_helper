<?php

namespace Tests\Unit;

use App\Dice\DiceRollResult;
use App\Dice\DiceService;
use App\Dice\Exceptions\InvalidDiceInputException;
use App\Enums\DiceRollMode;
use App\Enums\DiceRollOutcome;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DiceServiceTest extends TestCase
{
    private DiceService $dice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dice = new DiceService;
    }

    public function test_roll_sum_mode_sets_net_to_dice_total(): void
    {
        $result = DiceRollResult::fromDice(3, 6, 10, [4, 7, 10], DiceRollMode::Sum);

        $this->assertSame(DiceRollMode::Sum, $result->mode);
        $this->assertSame(21, $result->net);
        $this->assertSame(21, $result->toPayload()['sum']);
        $this->assertSame(DiceRollOutcome::Success, $result->outcome);
    }

    public function test_roll_returns_expected_shape(): void
    {
        $result = $this->dice->roll(3, 6, 10);

        $this->assertSame(3, $result->pool);
        $this->assertSame(6, $result->difficulty);
        $this->assertSame(10, $result->diceType);
        $this->assertCount(3, $result->dice);
        foreach ($result->dice as $value) {
            $this->assertIsInt($value);
            $this->assertGreaterThanOrEqual(1, $value);
            $this->assertLessThanOrEqual(10, $value);
        }
    }

    #[DataProvider('invalidRollProvider')]
    public function test_roll_rejects_invalid_input(int $pool, int $difficulty, int $diceType): void
    {
        $this->expectException(InvalidDiceInputException::class);

        $this->dice->roll($pool, $difficulty, $diceType);
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: int}>
     */
    public static function invalidRollProvider(): array
    {
        return [
            'pool zero' => [0, 6, 10],
            'pool over max' => [101, 6, 10],
            'bad dice type' => [2, 6, 7],
            'difficulty zero' => [2, 0, 10],
            'difficulty above dice type' => [2, 11, 10],
        ];
    }

    #[DataProvider('outcomeProvider')]
    public function test_from_dice_computes_outcome(array $dice, int $difficulty, DiceRollOutcome $outcome, int $net): void
    {
        $result = DiceRollResult::fromDice(count($dice), $difficulty, 10, $dice);

        $this->assertSame($outcome, $result->outcome);
        $this->assertSame($net, $result->net);
    }

    /**
     * @return array<string, array{0: list<int>, 1: int, 2: DiceRollOutcome, 3: int}>
     */
    public static function outcomeProvider(): array
    {
        return [
            'botch' => [[1, 2, 3], 6, DiceRollOutcome::Botch, -1],
            'failure no ones' => [[2, 3, 4], 6, DiceRollOutcome::Failure, 0],
            'success net' => [[8, 8, 1], 6, DiceRollOutcome::Success, 1],
            'failure ones cancel all successes' => [[8, 1, 1], 6, DiceRollOutcome::Failure, -1],
            'failure net zero' => [[8, 1, 4, 1, 10], 6, DiceRollOutcome::Failure, 0],
            'failure net negative' => [[1, 10, 1, 2, 3], 9, DiceRollOutcome::Failure, -1],
        ];
    }
}
