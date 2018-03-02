<?php
/**
 * @author Evgeniy Makhrov <emakhrov@gmail.com>
 */

namespace Le;

class GetoptTest extends \PHPUnit\Framework\TestCase {
    public static function providerGetoptSuccess() {
        return [
            [['le.php'], [], [], []],
            [['le.php', '-a'], ['a' => ''], ['a' => true], []],
            [['le.php', '-a', 'b'], ['a' => ':'], ['a' => 'b'], []],
            [['le.php', '-a', 'b', 'c'], ['a' => ':'], ['a' => 'b'], ['c']],
            [['le.php', '-a', 'b', 'c'], ['a' => '::'], ['a' => 'b'], ['c']],
            [['le.php', '-a'], ['a' => '::'], ['a' => true], []],
            [['le.php', '-a', 'b'], ['a' => ''], ['a' => true], ['b']],
            [['le.php', '--', '-a', 'b'], ['a' => ''], [], ['-a', 'b']],
        ];
    }

    /**
     * @dataProvider providerGetoptSuccess
     */
    public function testGetoptSuccess($args, $formal, $exp_o, $exp_no) {
        $G = new Getopt();
        [$o, $no] = $G->parse($args, $formal);
        self::assertSame($exp_o, $o, "options array");
        self::assertSame($exp_no, $no, "non options array");
    }

    public static function providerGetoptFailures() {
        return [
            [['le.php', '-a', 'b', '-a'], ['a' => ''], ['a' => null], ['b']],
        ];
    }

    /**
     * @dataProvider providerGetoptFailures
     */
    public function testGetoptFailures($args, $formal) {
        $G = new Getopt();
        $this->expectExceptionMessage("have flag -a after non-option");
        $G->parse($args, $formal);
    }
}
