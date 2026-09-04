<?php if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}  if (ROLE != 'admin') {
    msg('权限不足!');
}
global $i;
?>
<form action="setting.php?mod=admin:cron&add" method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (!empty($_GET['edit'])) {
        $edit = htmlspecialchars($_GET['edit'], ENT_QUOTES, 'UTF-8');
        echo '<h3>编辑计划任务：' . $edit  . '</h3><br/>';
    } else {
        echo '<h3>新建计划任务</h3><br/>';
    } ?>
    <div class="table-responsive">
        <?php if (isset($edit)) {
            echo '<input type="hidden" name="edit" value="' . $edit . '">';
        } ?>
        <table class="cs-table">
            <thead>
            <tr>
                <th style="width:25%">参数</th>
                <th>值</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>任务名称<br/>唯一，不能有中文</td>
                <td><input type="text" name="name" class="form-control" required="" <?php if (isset($edit)) {
                    echo 'readonly value="' . $edit . '"';
                                                                                    } ?>></td>
            </tr>
            <tr>
                <td>任务文件<br/>基准目录为云签到根目录，开头不需要带/</td>
                <td><input type="text" name="file" class="form-control" required="" <?php if (isset($edit)) {
                    echo 'value="' . htmlspecialchars($i['cron'][$edit]['file'], ENT_QUOTES, 'UTF-8') . '"';
                                                                                    } ?>></td>
            </tr>
            <tr>
                <td>任务描述<br/>描述这个任务</td>
                <td><textarea class="form-control" name="desc"><?php if (isset($edit)) {
                    echo htmlspecialchars($i['cron'][$edit]['desc'], ENT_QUOTES, 'UTF-8');
                                                               } ?></textarea></td>
            </tr>
            <tr>
                <td>忽略任务</td>
                <td><input type="radio" name="no" value="0" required="" <?php if (!isset($edit) || $i['cron'][$edit]['no'] != '1') {
                    echo 'checked';
                                                                        }; ?>> 否&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="no" value="1" required="" <?php if (isset($edit) && $i['cron'][$edit]['no'] == '1') {
                                                                        echo 'checked';
                                                                        }; ?>> 是</td>
            </tr>
            <tr>
                <td>执行间隔<br/>单位为秒，0为始终执行</td>
                <td><input type="number" name="freq" class="form-control" required="" value="<?php if (isset($edit)) {
                    echo htmlspecialchars($i['cron'][$edit]['freq'], ENT_QUOTES, 'UTF-8');
                                                                                             } else {
                                                                                                 echo '0';
                                                                                             } ?>"></td>
            </tr>
            <tr>
                <td>上次执行<br/>Unix 时间戳</td>
                <td><input type="number" name="lastdo" class="form-control" required="" value="<?php if (isset($edit)) {
                    echo htmlspecialchars($i['cron'][$edit]['lastdo'], ENT_QUOTES, 'UTF-8');
                                                                                               } else {
                                                                                                   echo time();
                                                                                               } ?>"></td>
            </tr>
            <tr>
                <td>执行日志<br/><br/>系统会自动写入</td>
                <td><textarea name="log" class="form-control" style="height:100px"><?php if (isset($edit)) {
                    echo htmlspecialchars($i['cron'][$edit]['log'], ENT_QUOTES, 'UTF-8');
                                                                                   } ?></textarea></td>
            </tr>
            <tr>
            <td>启用任务（V5.13）<br/>禁用后该任务不会被执行</td>
            <td><input type="radio" name="enable" value="1" required="" <?php if (!isset($edit) || (isset($i['cron'][$edit]['enable']) && (int)$i['cron'][$edit]['enable'] !== 0)) {
                echo 'checked';
            }; ?>> 是&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="enable" value="0" required="" <?php if (isset($edit) && isset($i['cron'][$edit]['enable']) && (int)$i['cron'][$edit]['enable'] === 0) {
                echo 'checked';
            }; ?>> 否</td>
            </tr>
            <tr>
            <td>最大重试次数（V5.13）<br/>失败后自动重试次数，超出后进入正常周期并通知</td>
            <td><input type="number" name="max_retry" class="form-control" min="0" max="20" value="<?php if (isset($edit) && isset($i['cron'][$edit]['max_retry'])) {
                echo htmlspecialchars($i['cron'][$edit]['max_retry'], ENT_QUOTES, 'UTF-8');
            } else {
                echo '2';
            } ?>"></td>
            </tr>
            </tbody>
        </table>
    </div>
    <br/><button type="submit" class="cs-btn cs-btn-primary">提交更改</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <button type="button" class="cs-btn cs-btn-secondary" onclick="location = 'index.php?mod=admin:cron'">取消</button>
</form>
