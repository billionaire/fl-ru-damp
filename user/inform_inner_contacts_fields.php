<?php

    $value_map = array(
        'site'         => '����:',   'site_1'   => '����:',   'site_2'   => '����:',   'site_3'    => '����:', 
        'icq'          => 'ICQ:',    'icq_1'    => 'ICQ:',    'icq_2'    => 'ICQ:',    'icq_3'     => 'ICQ:',
        'jabber'       => 'Jabber:', 'jabber_1' => 'Jabber:', 'jabber_2' => 'Jabber:', 'jabber_3'  => 'Jabber:',
        'ljuser'       => 'LiveJournal user:', 'lj_1' => 'LiveJournal user:', 'lj_2' => 'LiveJournal user:', 'lj_3' => 'LiveJournal user:',
        'skype'        => 'Skype:',   'skype_1' => 'Skype:',   'skype_2' => 'Skype:',   'skype_3'  => 'Skype:',
        'second_email' => 'E-mail:',  'email_1' => 'E-mail:',  'email_2' => 'E-mail:',  'email_3'  => 'E-mail:',
        'phone'        => '�������:', 'phone_1' => '�������:', 'phone_2' => '�������:', 'phone_3'  => '�������:'
    );?>

    <?php foreach($value_map as $vmap => $vname) {
        if($val = $user->$vmap) { $d = "{$vmap}_edit_date"; $edit_date = strtotime($user->$d); ?>
            <tr>
                <th><?= $vname; ?></th>
                <td>
                    <?= ( ($info_for_reg[$vmap] && !$uid) ? $reg_string : initContactData($vmap, $val, ($user->uid != $uid)) );?>
                </td>
                <td>
                <?php if ($edit_date) { ?>
                    <img src="<?= strtotime('+7 days', $edit_date) > time() ? '/images/ico-e-a.png' : '/images/ico-e-o.png' ?>" alt="��������������� <?= date('d.m.Y � H.i', $edit_date); ?>"  title="��������������� <?= date('d.m.Y � H.i', $edit_date); ?>" />
                <?php } //if ?>    
                </td>
            </tr>
        <?php } //if ?>
    <?php } //foreach?>