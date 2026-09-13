<?php

declare(strict_types=1);

namespace App\Services\Bess;

use App\Models\Project;
use Carbon\Carbon;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\AutoShape;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Border;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds a single-slide .pptx Summary Gantt with native PowerPoint shapes.
 *
 * Layout mirrors the SVG view: milestones at top, then a coloured timeline
 * band carrying the year labels, then the five discipline bands (Design,
 * Civil, Mechanical, Electrical, Commissioning). Vertical leader lines
 * drop from each milestone through the timeline band into the bar area.
 */
final class SummaryGanttPowerPointService
{
    public function __construct(
        private readonly SummaryGanttLayout $layout,
    ) {}

    private const SLIDE_W = 960;
    private const SLIDE_H = 540;

    private const PLOT_LEFT = 110;
    private const PLOT_RIGHT = 930;
    private const PLOT_WIDTH = self::PLOT_RIGHT - self::PLOT_LEFT;

    private const MILESTONE_LABEL_TOP = 80;
    private const MILESTONE_Y = 128;
    private const TIMELINE_TOP = 142;
    private const TIMELINE_BOTTOM = 168;

    private const BAR_HEIGHT = 24;
    private const BAR_DESIGN_Y = 178;
    private const BAR_CIVIL_Y = 210;
    private const BAR_MECHANICAL_Y = 242;
    private const BAR_ELECTRICAL_Y = 274;
    private const BAR_COMMISSIONING_Y = 306;
    private const PLOT_BOTTOM = 340;

    private const BAR_COLOURS = [
        'Design' => 'FF6366F1',
        'Civil' => 'FF92400E',
        'Mechanical' => 'FF2563EB',
        'Electrical' => 'FFDC2626',
        'Commissioning' => 'FF059669',
    ];

    private const BAR_Y = [
        'Design' => self::BAR_DESIGN_Y,
        'Civil' => self::BAR_CIVIL_Y,
        'Mechanical' => self::BAR_MECHANICAL_Y,
        'Electrical' => self::BAR_ELECTRICAL_Y,
        'Commissioning' => self::BAR_COMMISSIONING_Y,
    ];

    public function download(Project $project): StreamedResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gantt_').'.pptx';
        $this->writeFile($project, $tmp);

        $filename = sprintf('Summary-Gantt-%s.pptx', $project->code ?? $project->id);

        return response()->streamDownload(function () use ($tmp) {
            echo file_get_contents($tmp);
            @unlink($tmp);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ]);
    }

    public function writeFile(Project $project, string $path): void
    {
        $layout = $this->layout->build($project);

        $pres = new PhpPresentation();
        $pres->getDocumentProperties()
            ->setCreator('BESS Schedule Generator')
            ->setTitle('Summary Gantt — '.$project->name)
            ->setSubject('Summary Gantt')
            ->setDescription('User-entered key dates rendered as a single-slide summary Gantt.');

        $pres->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9, true);

        $slide = $pres->getActiveSlide();

        $this->addAccentStrip($slide);
        $this->addTitle($slide, $project);

        if (! $layout['has_window']) {
            $this->addPlaceholder($slide);
            IOFactory::createWriter($pres, 'PowerPoint2007')->save($path);

            return;
        }

        $this->addLeaderLines($slide, $layout);
        $this->addPlotBackground($slide);
        $this->addTimelineBand($slide, $layout);
        $this->addRowLabels($slide);
        $this->addBars($slide, $layout);
        $this->addMilestones($slide, $layout);
        $this->addLegend($slide);
        $this->addFooter($slide, $project, $layout);

        IOFactory::createWriter($pres, 'PowerPoint2007')->save($path);
    }

    private function addAccentStrip(Slide $slide): void
    {
        $strip = $slide->createAutoShape()
            ->setType(AutoShape::TYPE_RECTANGLE)
            ->setWidth(self::SLIDE_W)
            ->setHeight(6)
            ->setOffsetX(0)
            ->setOffsetY(0);
        $strip->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FF1E3A8A'));
        $strip->getOutline()->setWidth(0);
    }

    private function addTitle(Slide $slide, Project $project): void
    {
        $title = $slide->createRichTextShape()
            ->setWidth(self::SLIDE_W - 80)
            ->setHeight(32)
            ->setOffsetX(40)
            ->setOffsetY(20);
        $run = $title->createTextRun('Summary Gantt — '.$project->name);
        $run->getFont()->setBold(true)->setSize(22)->setColor(new Color('FF111827'));

        $sub = $slide->createRichTextShape()
            ->setWidth(self::SLIDE_W - 80)
            ->setHeight(16)
            ->setOffsetX(40)
            ->setOffsetY(54);
        $subRun = $sub->createTextRun(
            'Code '.($project->code ?? '—').'   ·   Generated '.now()->format('d M Y')
        );
        $subRun->getFont()->setSize(11)->setColor(new Color('FF6B7280'));
    }

    private function addPlaceholder(Slide $slide): void
    {
        $box = $slide->createRichTextShape()
            ->setWidth(self::SLIDE_W - 80)
            ->setHeight(40)
            ->setOffsetX(40)
            ->setOffsetY(240);
        $run = $box->createTextRun('Not enough dates to render a Gantt. Open the project edit form and fill in the Key Dates section.');
        $run->getFont()->setSize(14)->setColor(new Color('FF6B7280'));
    }

    /** @param array<string, mixed> $layout */
    private function addLeaderLines(Slide $slide, array $layout): void
    {
        $start = $layout['start'];
        $totalDays = $layout['total_days'];
        $x = fn (Carbon $d): float => self::PLOT_LEFT + ($start->diffInDays($d) / $totalDays) * self::PLOT_WIDTH;

        foreach ($layout['milestones'] as $m) {
            $cx = (int) round($x($m['date']));

            $line = $slide->createLineShape($cx, self::MILESTONE_Y + 8, $cx, self::PLOT_BOTTOM);
            $line->getBorder()->setLineWidth(1);
            $line->getBorder()->setLineStyle(Border::LINE_SINGLE);
            $line->getBorder()->setDashStyle(Border::DASH_DASH);
            $line->getBorder()->setColor(new Color('FF94A3B8'));
        }
    }

    private function addPlotBackground(Slide $slide): void
    {
        $bg = $slide->createAutoShape()
            ->setType(AutoShape::TYPE_RECTANGLE)
            ->setWidth(self::PLOT_WIDTH)
            ->setHeight(self::PLOT_BOTTOM - self::TIMELINE_BOTTOM)
            ->setOffsetX(self::PLOT_LEFT)
            ->setOffsetY(self::TIMELINE_BOTTOM);
        $bg->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFFAFAFA'));
        $bg->getOutline()->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFE5E7EB'));
        $bg->getOutline()->setWidth(1);
    }

    /** @param array<string, mixed> $layout */
    private function addTimelineBand(Slide $slide, array $layout): void
    {
        $start = $layout['start'];
        $totalDays = $layout['total_days'];
        $x = fn (Carbon $d): float => self::PLOT_LEFT + ($start->diffInDays($d) / $totalDays) * self::PLOT_WIDTH;

        $band = $slide->createAutoShape()
            ->setType(AutoShape::TYPE_RECTANGLE)
            ->setWidth(self::PLOT_WIDTH)
            ->setHeight(self::TIMELINE_BOTTOM - self::TIMELINE_TOP)
            ->setOffsetX(self::PLOT_LEFT)
            ->setOffsetY(self::TIMELINE_TOP);
        $band->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FF1E3A8A'));
        $band->getOutline()->setWidth(0);

        foreach ($layout['axis_ticks'] as $i => $tick) {
            $tickX = (int) round($x($tick['date']));

            if ($i > 0) {
                $div = $slide->createLineShape($tickX, self::TIMELINE_TOP, $tickX, self::TIMELINE_BOTTOM);
                $div->getBorder()->setLineWidth(1);
                $div->getBorder()->setLineStyle(Border::LINE_SINGLE);
                $div->getBorder()->setColor(new Color('FF60A5FA'));
            }

            $label = $slide->createRichTextShape()
                ->setWidth(60)
                ->setHeight(16)
                ->setOffsetX($tickX + 4)
                ->setOffsetY(self::TIMELINE_TOP + 4);
            $run = $label->createTextRun($tick['label']);
            $run->getFont()->setSize(12)->setBold(true)->setColor(new Color('FFFFFFFF'));
        }
    }

    private function addRowLabels(Slide $slide): void
    {
        foreach (self::BAR_Y as $name => $y) {
            $row = $slide->createRichTextShape()
                ->setWidth(self::PLOT_LEFT - 20)
                ->setHeight(self::BAR_HEIGHT)
                ->setOffsetX(10)
                ->setOffsetY($y);
            $row->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $r = $row->createTextRun($name);
            $r->getFont()->setSize(10)->setBold(true)->setColor(new Color('FF374151'));
        }
    }

    /** @param array<string, mixed> $layout */
    private function addBars(Slide $slide, array $layout): void
    {
        $start = $layout['start'];
        $totalDays = $layout['total_days'];
        $x = fn (Carbon $d): float => self::PLOT_LEFT + ($start->diffInDays($d) / $totalDays) * self::PLOT_WIDTH;

        foreach ($layout['bars'] as $bar) {
            $colour = self::BAR_COLOURS[$bar['name']] ?? 'FF6B7280';
            $y = self::BAR_Y[$bar['name']] ?? self::BAR_DESIGN_Y;
            $left = (int) round($x($bar['start']));
            $right = (int) round($x($bar['finish']));
            $width = max(4, $right - $left);

            $rect = $slide->createAutoShape()
                ->setType(AutoShape::TYPE_ROUNDED_RECTANGLE)
                ->setWidth($width)
                ->setHeight(self::BAR_HEIGHT)
                ->setOffsetX($left)
                ->setOffsetY($y);
            $rect->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($colour));
            $rect->getOutline()->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($colour));
            $rect->getOutline()->setWidth(1);

            if ($width > 60) {
                $rect->setText($bar['label'].' ('.$bar['duration_days'].'d)');
            } else {
                $rect->setText('('.$bar['duration_days'].'d)');
            }
        }
    }

    /** @param array<string, mixed> $layout */
    private function addMilestones(Slide $slide, array $layout): void
    {
        $start = $layout['start'];
        $totalDays = $layout['total_days'];
        $x = fn (Carbon $d): float => self::PLOT_LEFT + ($start->diffInDays($d) / $totalDays) * self::PLOT_WIDTH;

        foreach ($layout['milestones'] as $m) {
            $cx = (int) round($x($m['date']));
            $size = 14;

            $label = $slide->createRichTextShape()
                ->setWidth(110)
                ->setHeight(36)
                ->setOffsetX($cx - 55)
                ->setOffsetY(self::MILESTONE_LABEL_TOP);
            $label->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $name = $label->createTextRun($m['short']);
            $name->getFont()->setSize(10)->setBold(true)->setColor(new Color('FF1F2937'));

            $br = $label->createParagraph();
            $br->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $date = $br->createTextRun($m['date']->format('d M Y'));
            $date->getFont()->setSize(9)->setColor(new Color('FF6B7280'));

            $diamond = $slide->createAutoShape()
                ->setType(AutoShape::TYPE_DIAMOND)
                ->setWidth($size)
                ->setHeight($size)
                ->setOffsetX($cx - intdiv($size, 2))
                ->setOffsetY(self::MILESTONE_Y - intdiv($size, 2));
            $diamond->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->setStartColor(new Color($this->hexToArgb($m['colour'])));
            $diamond->getOutline()->getFill()->setFillType(Fill::FILL_SOLID)
                ->setStartColor(new Color('FFFFFFFF'));
            $diamond->getOutline()->setWidth(1);
        }
    }

    private function addLegend(Slide $slide): void
    {
        $items = [
            ['Design', self::BAR_COLOURS['Design']],
            ['Civil', self::BAR_COLOURS['Civil']],
            ['Mechanical', self::BAR_COLOURS['Mechanical']],
            ['Electrical', self::BAR_COLOURS['Electrical']],
            ['Commissioning', self::BAR_COLOURS['Commissioning']],
        ];

        $startX = 60;
        $y = 380;
        $chipW = 14;
        $chipH = 14;
        $cellW = 130;

        foreach ($items as $i => [$label, $colour]) {
            $cx = $startX + $i * $cellW;

            $chip = $slide->createAutoShape()
                ->setType(AutoShape::TYPE_ROUNDED_RECTANGLE)
                ->setWidth($chipW)
                ->setHeight($chipH)
                ->setOffsetX($cx)
                ->setOffsetY($y + 1);
            $chip->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($colour));
            $chip->getOutline()->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($colour));
            $chip->getOutline()->setWidth(1);

            $text = $slide->createRichTextShape()
                ->setWidth($cellW - $chipW - 8)
                ->setHeight(18)
                ->setOffsetX($cx + $chipW + 6)
                ->setOffsetY($y);
            $r = $text->createTextRun($label);
            $r->getFont()->setSize(11)->setBold(true)->setColor(new Color('FF374151'));
        }
    }

    /** @param array<string, mixed> $layout */
    private function addFooter(Slide $slide, Project $project, array $layout): void
    {
        $window = $slide->createRichTextShape()
            ->setWidth(self::SLIDE_W - 80)
            ->setHeight(16)
            ->setOffsetX(40)
            ->setOffsetY(420);
        $w = $window->createTextRun(sprintf(
            'Window: %s → %s   ·   %d days (~%s months)',
            $layout['start']->format('d M Y'),
            $layout['finish']->format('d M Y'),
            $layout['total_days'],
            number_format($layout['total_days'] / 30.44, 1),
        ));
        $w->getFont()->setSize(11)->setBold(true)->setColor(new Color('FF374151'));

        $parts = [];
        foreach ($layout['bars'] as $bar) {
            $parts[] = $bar['label'].' '.$bar['duration_days'].'d';
        }

        if (! empty($parts)) {
            $durations = $slide->createRichTextShape()
                ->setWidth(self::SLIDE_W - 80)
                ->setHeight(16)
                ->setOffsetX(40)
                ->setOffsetY(442);
            $d = $durations->createTextRun(implode('   ·   ', $parts));
            $d->getFont()->setSize(10)->setColor(new Color('FF6B7280'));
        }

        $brand = $slide->createRichTextShape()
            ->setWidth(self::SLIDE_W - 80)
            ->setHeight(14)
            ->setOffsetX(40)
            ->setOffsetY(518);
        $b = $brand->createTextRun('BESS Schedule Generator');
        $b->getFont()->setSize(8)->setItalic(true)->setColor(new Color('FF9CA3AF'));
    }

    private function hexToArgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return strlen($hex) === 6 ? 'FF'.strtoupper($hex) : strtoupper($hex);
    }
}