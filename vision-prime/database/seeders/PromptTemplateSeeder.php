<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Content\Models\PromptTemplate;

class PromptTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'title' => 'راهنمای جامع آموزشی',
                'slug' => 'tutorial-comprehensive',
                'content_type' => 'article',
                'subtype' => 'tutorial',
                'tone' => 'informative',
                'system_prompt' => 'تو یک متخصص سئو و کپی‌رایتر فارسی هستی. مقاله آموزشی جامع و عملی بنویس.',
                'user_prompt_template' => 'مقاله آموزشی جامع درباره "{title}" بنویس. شامل مقدمه، مراحل گام‌به‌گام، مثال‌های عملی، FAQ و نتیجه‌گیری باشد.',
                'is_featured' => true,
                'tags' => ['آموزشی', 'راهنما', 'گام‌به‌گام'],
            ],
            [
                'title' => 'مقایسه و بررسی محصول',
                'slug' => 'product-comparison',
                'content_type' => 'article',
                'subtype' => 'comparison',
                'tone' => 'neutral',
                'system_prompt' => 'تو یک منتقد حرفه‌ای محصول هستی. مقایسه منصفانه و جامع بنویس.',
                'user_prompt_template' => 'مقایسه جامع "{title}" بنویس. شامل مشخصات فنی، مزایا و معایب، جدول مقایسه، و توصیه نهایی باشد.',
                'is_featured' => true,
                'tags' => ['مقایسه', 'بررسی', 'محصول'],
            ],
            [
                'title' => 'مقاله خبری و تحلیلی',
                'slug' => 'news-analysis',
                'content_type' => 'article',
                'subtype' => 'news',
                'tone' => 'professional',
                'system_prompt' => 'تو یک خبرنگار و تحلیلگر حرفه‌ای هستی. خبر را با تحلیل عمیق بنویس.',
                'user_prompt_template' => 'مقاله تحلیلی درباره "{title}" بنویس. شامل خلاصه خبر، تحلیل تاثیر، نظرات کارشناسان و پیش‌بینی آینده باشد.',
                'is_featured' => false,
                'tags' => ['خبری', 'تحلیلی', 'رویداد'],
            ],
            [
                'title' => 'مقاله فروش و تبلیغاتی',
                'slug' => 'sales-persuasive',
                'content_type' => 'article',
                'subtype' => 'sales',
                'tone' => 'persuasive',
                'system_prompt' => 'تو یک کپی‌رایتر فروش حرفه‌ای هستی. محتوای جذاب و تبدیل‌کننده بنویس.',
                'user_prompt_template' => 'مقاله فروش جذاب درباره "{title}" بنویس. شامل معرفی محصول، مزایای کلیدی، شواهد اجتماعی، و CTA قوی باشد.',
                'is_featured' => false,
                'tags' => ['فروش', 'تبلیغاتی', 'تبدیل'],
            ],
            [
                'title' => 'مقاله بررسی و نقد',
                'slug' => 'review-detailed',
                'content_type' => 'article',
                'subtype' => 'review',
                'tone' => 'professional',
                'system_prompt' => 'تو یک منتقد حرفه‌ای و بی‌طرف هستی. بررسی دقیق و منصفانه بنویس.',
                'user_prompt_template' => 'بررسی تخصصی "{title}" بنویس. شامل معرفی، نقاط قوت و ضعف، امتیازدهی، و توصیه نهایی باشد.',
                'is_featured' => true,
                'tags' => ['نقد', 'بررسی', 'امتیاز'],
            ],
        ];

        foreach ($templates as $tpl) {
            PromptTemplate::updateOrCreate(
                ['slug' => $tpl['slug']],
                $tpl
            );
        }
        
        $this->command->info('Prompt templates seeded successfully!');
    }
}
