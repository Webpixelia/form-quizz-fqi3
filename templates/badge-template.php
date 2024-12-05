<?php
/**
 * Template for rendering badges
 *
 * @var array $badges_data Array of badge data
 * @var array $data Array of pre-escaped static strings
 */
?>
<div class="legend-badges">
    <p class="large-p ft-w600"><?php echo $title; ?></p>
    <div class="x-scroll">
        <table class="fqi3-legend-badges-list">
            <thead>
                <tr>
                    <th align="center"><?php echo $badge_type; ?></th>
                    <th align="center"><?php echo $how_to_get ?></th>
                    <th align="center" style="width: 50%;min-width: 300px;"><?php echo $badges_versions; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($badges_data as $type_badge): ?>
                    <tr>
                        <td align="center"><?php echo esc_html($type_badge['type']); ?></td>
                        <td align="center">
                            <?php printf($how_to_get_prefix, esc_html($type_badge['howget'])); ?>
                        </td>
                        <td align="center">
                            <table style="width: 100%; table-layout: fixed;">
                                <thead>
                                    <tr>
                                        <?php foreach ($type_badge['names'] as $name): ?>
                                            <th><?php echo esc_html($name); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <?php foreach ($type_badge['images'] as $index => $image): ?>
                                            <td align="center">
                                                <?php
                                                $attachment_url = wp_get_attachment_url((int)$image);
                                                if ($attachment_url): ?>
                                                    <img src="<?php echo esc_url($attachment_url); ?>"
                                                        alt="<?php echo esc_attr($type_badge['names'][$index] ?? ''); ?>" />
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <?php foreach ($type_badge['thresholds'] as $threshold): ?>
                                            <td align="center">
                                                <?php
                                                echo esc_html($threshold ?? $na) . ' ' . esc_html($type_badge['unity'] ?? '');
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>