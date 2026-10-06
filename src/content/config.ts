import { defineCollection, z } from 'astro:content';
export const categories = ['Writing', 'Research', 'Editing & Quality', 'Data & Technical'] as const;
const projects = defineCollection({
  type: 'content',
  schema: z.object({
    title: z.string(),
    date: z.coerce.date(),
    category: z.enum(categories),
    excerpt: z.string(),
    cover: z.string().optional(),
    coverAlt: z.string().default(''),
    video: z.string().optional(),
    videoFile: z.string().optional(),
    featured: z.boolean().default(false),
  }),
});
export const collections = { projects };
