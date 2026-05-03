<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Response;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

class ShareController extends Controller
{
    private const FONT_CJK  = '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc';
    private const FONT_BOLD = '/usr/share/fonts/opentype/noto/NotoSansCJK-Bold.ttc';
    private const FONT_REG  = '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc';

    public function show(User $user): \Illuminate\View\View
    {
        $approvedCount = $user->reports()->where('status', Report::STATUS_APPROVED)->count();
        $totalCount    = $user->reports()->count();
        $rank          = $this->computeRank($approvedCount);

        return view('pages.share', compact('user', 'approvedCount', 'totalCount', 'rank'));
    }

    public function image(User $user): Response
    {
        $approvedCount = $user->reports()->where('status', Report::STATUS_APPROVED)->count();
        $rank          = $this->computeRank($approvedCount);

        $manager = new ImageManager(new GdDriver());
        $canvas  = $manager->createImage(1200, 630);

        // Background
        $canvas->fill('#0d1117');

        // Red left accent bar
        $canvas->drawRectangle(function ($rect) {
            $rect->size(10, 630)->at(0, 0)->background('#dc2626');
        });

        // Decorative concentric circles (right side)
        $canvas->drawEllipse(function ($ellipse) {
            $ellipse->size(480, 480)->at(980, 315)->border('#1e293b', 2);
        });
        $canvas->drawEllipse(function ($ellipse) {
            $ellipse->size(300, 300)->at(980, 315)->border('#1e2940', 2);
        });

        // Big number (approved count)
        $canvas->text((string) $approvedCount, 80, 160, function (FontFactory $f) {
            $f->filename(self::FONT_BOLD);
            $f->size(200);
            $f->color('#dc2626');
            $f->align('left', 'top');
        });

        // "件核准通報" label (CJK)
        $canvas->text('件核准通報', 80, 390, function (FontFactory $f) {
            $f->filename(self::FONT_CJK);
            $f->size(44);
            $f->color('#94a3b8');
            $f->align('left', 'top');
        });

        // Username
        $displayName = mb_substr($user->name, 0, 16);
        $canvas->text($displayName, 80, 460, function (FontFactory $f) {
            $f->filename(self::FONT_CJK);
            $f->size(52);
            $f->color('#f1f5f9');
            $f->align('left', 'top');
        });

        // Rank badge (top-right)
        if ($rank <= 20) {
            $canvas->drawRectangle(function ($rect) {
                $rect->size(200, 80)->at(960, 40)->background('#7f1d1d')->border('#dc2626', 1);
            });

            $rankLabel = sprintf('# %d', $rank);
            $canvas->text($rankLabel, 1060, 80, function (FontFactory $f) {
                $f->filename(self::FONT_BOLD);
                $f->size(32);
                $f->color('#fca5a5');
                $f->align('center', 'bottom');
            });
        }

        // Mouse Radar branding (bottom-right)
        $canvas->text('ratdar.taipei', 1160, 600, function (FontFactory $f) {
            $f->filename(self::FONT_REG);
            $f->size(22);
            $f->color('#334155');
            $f->align('right', 'bottom');
        });

        $encoded = $canvas->encode(new JpegEncoder(quality: 88));

        return response($encoded->toString(), 200, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    // Count users whose approved count is strictly greater → rank of $user
    private function computeRank(int $approvedCount): int
    {
        $better = \DB::table('reports')
            ->select('user_id')
            ->where('status', Report::STATUS_APPROVED)
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > ?', [$approvedCount])
            ->count();

        return $better + 1;
    }
}
